<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderTrackingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['reference' => 'required|string|max:50', 'mobile' => ['required', 'string', 'max:24', 'regex:/^\+?[0-9\s()-]+$/']]);
        $mobile = preg_replace('/\D/', '', $data['mobile']);
        if (strlen($mobile) < 10 || strlen($mobile) > 13) {
            throw ValidationException::withMessages(['mobile' => 'Enter a valid mobile number.']);
        }
        $order = Order::query()->where('reference', strtoupper(trim($data['reference'])))->whereRaw("REPLACE(REPLACE(mobile, '+', ''), ' ', '') = ?", [$mobile])->first();
        abort_unless($order, 404, 'We could not find an order matching those details.');

        return response()->json(['data' => [
            'reference' => $order->reference,
            'status' => $order->status,
            'prescription_status' => $order->prescription_status,
            'pricing_status' => $order->pricing_status,
            'subtotal' => $order->subtotal !== null ? (float) $order->subtotal : null,
            'item_count' => $order->items()->sum('quantity'),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'history' => $order->statusHistory()->oldest()->get(['status', 'note', 'created_at']),
        ]]);
    }
}
