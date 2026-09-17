<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\WhatsAppOrderMessage;
use Illuminate\Http\RedirectResponse;

class OrderWhatsAppController extends Controller
{
    public function __invoke(string $publicId, WhatsAppOrderMessage $whatsapp): RedirectResponse
    {
        $order = Order::where('public_id', $publicId)->with('items')->firstOrFail();
        $url = $whatsapp->url($order);
        abort_unless($url, 503, 'WhatsApp ordering is not configured.');
        $order->update(['whatsapp_redirected_at' => now()]);

        return redirect()->away($url);
    }
}
