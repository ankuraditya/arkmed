<?php

namespace App\Services;

use App\Models\Order;

class WhatsAppOrderMessage
{
    public function build(Order $order): string
    {
        $lines = ['Hello ARK med,', '', 'I would like to place an order.', "Order Reference: {$order->reference}", '', 'Medicines:'];
        foreach ($order->items as $index => $item) {
            $lines[] = ($index + 1).". {$item->name_snapshot}".($item->strength_snapshot ? " {$item->strength_snapshot}" : '')." - Qty: {$item->quantity}";
        } $lines[] = '';
        $lines[] = 'Prescription: '.str_replace('_', ' ', $order->prescription_status);
        $lines[] = "Customer: {$order->customer_name}";
        $lines[] = "Mobile: {$order->mobile}";
        $lines[] = "Address: {$order->address_line_1}, {$order->city}, {$order->state} - {$order->pincode}";
        $lines[] = $order->subtotal !== null ? "Provisional total: ₹{$order->subtotal}" : 'Please confirm availability and final amount.';
        if ($order->customer_note) {
            $lines[] = "Customer note: {$order->customer_note}";
        }

return implode("\n", $lines);
    }

    public function url(Order $order): ?string
    {
        $number = preg_replace('/\D/', '', (string) config('services.whatsapp.number'));
        if (! $number) {
            return null;
        }

return 'https://wa.me/'.$number.'?text='.rawurlencode($this->build($order));
    }
}
