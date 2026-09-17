<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $openStatuses = ['new', 'contacted', 'confirmed', 'preparing', 'ready', 'dispatched'];

        return response()->json(['data' => [
            'active_medicines' => Medicine::where('is_active', true)->count(),
            'low_stock_medicines' => Medicine::where('is_active', true)->where('stock_status', 'low_stock')->count(),
            'unavailable_medicines' => Medicine::where('is_active', true)->where('stock_status', 'out_of_stock')->count(),
            'prescription_medicines' => Medicine::where('is_active', true)->where('requires_prescription', true)->count(),
            'orders_today' => Order::whereDate('created_at', today())->count(),
            'orders_last_7_days' => Order::where('created_at', '>=', now()->subDays(7))->count(),
            'open_orders' => Order::whereIn('status', $openStatuses)->count(),
            'ready_orders' => Order::where('status', 'ready')->count(),
            'revenue_today' => (float) Order::whereDate('created_at', today())->whereNotIn('status', ['cancelled', 'unable_to_fulfil'])->sum('subtotal'),
            'revenue_last_7_days' => (float) Order::where('created_at', '>=', now()->subDays(7))->whereNotIn('status', ['cancelled', 'unable_to_fulfil'])->sum('subtotal'),
            'pending_prescriptions' => Prescription::whereIn('status', ['pending', 'reviewed', 'more_info_required'])->count(),
            'new_enquiries' => Enquiry::where('status', 'new')->count(),
            'order_status_counts' => Order::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'recent_orders' => Order::latest()->limit(5)->get(['id', 'reference', 'customer_name', 'status', 'subtotal', 'created_at']),
            'recent_enquiries' => Enquiry::latest()->limit(5)->get(['id', 'type', 'name', 'status', 'created_at']),
            'oldest_pending_prescription_at' => Prescription::where('status', 'pending')->oldest()->value('created_at'),
            'generated_at' => now(),
        ]]);
    }
}
