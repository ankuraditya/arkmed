<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\Setting;
use App\Models\TemporaryPrescriptionUpload;
use App\Services\WhatsAppOrderMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, WhatsAppOrderMessage $whatsapp): JsonResponse
    {
        $data = $request->validated();
        $ordering = Setting::where('key', 'ordering_enabled')->first();
        abort_if($ordering && $ordering->typedValue() === false, 503, 'Online ordering is temporarily unavailable. Please contact ARK med.');
        $requested = collect($data['items']);
        $medicines = Medicine::query()->where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true))->whereIn('public_id', $requested->pluck('medicine_id'))->get()->keyBy('public_id');
        if ($medicines->count() !== $requested->pluck('medicine_id')->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'One or more medicines are unavailable.']);
        }
        $requiresPrescription = $requested->contains(fn ($line) => $medicines[$line['medicine_id']]->requires_prescription);
        if ($requiresPrescription && empty($data['prescription_upload_tokens'])) {
            throw ValidationException::withMessages(['prescription_upload_tokens' => 'A prescription is required for this order.']);
        }
        $uploads = TemporaryPrescriptionUpload::query()
            ->whereIn('token', $data['prescription_upload_tokens'] ?? [])
            ->whereNull('claimed_at')
            ->where('expires_at', '>', now())
            ->get();
        if (count($data['prescription_upload_tokens'] ?? []) !== $uploads->count()) {
            throw ValidationException::withMessages(['prescription_upload_tokens' => 'One or more prescription uploads are invalid or expired.']);
        }
        $order = DB::transaction(function () use ($data, $requested, $medicines, $requiresPrescription, $uploads) {
            $priced = true;
            $subtotal = 0;
            foreach ($requested as $line) {
                $medicine = $medicines[$line['medicine_id']];
                $max = $medicine->max_order_quantity ?? 10;
                if ($line['quantity'] > $max) {
                    throw ValidationException::withMessages(['items' => "Maximum quantity for {$medicine->name} is {$max}."]);
                }
                if ($medicine->stock_status === 'out_of_stock' && ! $medicine->allow_backorder) {
                    throw ValidationException::withMessages(['items' => "{$medicine->name} is currently unavailable."]);
                }
                if ($medicine->stock_qty !== null && $line['quantity'] > $medicine->stock_qty && ! $medicine->allow_backorder) {
                    throw ValidationException::withMessages(['items' => "Only {$medicine->stock_qty} unit(s) of {$medicine->name} are currently available."]);
                }
                if ($medicine->selling_price === null) {
                    $priced = false;
                } else {
                    $subtotal += (float) $medicine->selling_price * $line['quantity'];
                }
            }
            $order = Order::create(['public_id' => (string) Str::ulid(), 'reference' => $this->reference(), 'status' => 'new', 'prescription_status' => $requiresPrescription ? 'pending' : 'not_required', 'customer_name' => $data['customer']['name'], 'mobile' => preg_replace('/\s/', '', $data['customer']['mobile']), 'email' => $data['customer']['email'] ?? null, 'address_line_1' => $data['address']['line1'], 'address_line_2' => $data['address']['line2'] ?? null, 'landmark' => $data['address']['landmark'] ?? null, 'city' => $data['address']['city'], 'state' => $data['address']['state'], 'pincode' => $data['address']['pincode'], 'customer_note' => $data['customer_note'] ?? null, 'subtotal' => $priced ? $subtotal : null, 'pricing_status' => $priced ? 'priced' : 'confirm_on_whatsapp', 'source' => 'web']);
            foreach ($requested as $line) {
                $m = $medicines[$line['medicine_id']];
                $price = $m->selling_price !== null ? (float) $m->selling_price : null;
                $order->items()->create(['medicine_id' => $m->id, 'sku_snapshot' => $m->sku, 'name_snapshot' => $m->name, 'strength_snapshot' => $m->strength, 'unit_price' => $price, 'quantity' => $line['quantity'], 'line_total' => $price !== null ? $price * $line['quantity'] : null, 'requires_prescription_snapshot' => $m->requires_prescription]);
            }
            $order->statusHistory()->create(['status' => 'new']);
            if ($uploads->isNotEmpty()) {
                $prescription = Prescription::create(['public_id' => (string) Str::ulid(), 'order_id' => $order->id, 'status' => 'pending']);
                foreach ($uploads as $upload) {
                    $prescription->files()->create($upload->only(['disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'checksum']));
                    $upload->update(['claimed_at' => now()]);
                }
            }

            return $order->load('items');
        });

        return response()->json(['data' => ['public_id' => $order->public_id, 'reference' => $order->reference, 'status' => $order->status, 'prescription_status' => $order->prescription_status, 'pricing_status' => $order->pricing_status, 'subtotal' => $order->subtotal !== null ? (float) $order->subtotal : null, 'item_count' => $order->items->sum('quantity'), 'whatsapp_url' => $whatsapp->url($order) ? URL::temporarySignedRoute('orders.whatsapp', now()->addHours(24), ['publicId' => $order->public_id]) : null], 'message' => 'Order created. Continue on WhatsApp to confirm availability and final amount.'], 201);
    }

    private function reference(): string
    {
        do {
            $reference = 'ARK-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::where('reference', $reference)->exists());

        return $reference;
    }
}
