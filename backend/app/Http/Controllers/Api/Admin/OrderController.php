<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    private const STATUSES = ['new', 'contacted', 'confirmed', 'preparing', 'ready', 'dispatched', 'completed', 'cancelled', 'unable_to_fulfil'];

    private const TRANSITIONS = ['new' => ['contacted', 'confirmed', 'cancelled', 'unable_to_fulfil'], 'contacted' => ['confirmed', 'cancelled', 'unable_to_fulfil'], 'confirmed' => ['preparing', 'cancelled', 'unable_to_fulfil'], 'preparing' => ['ready', 'cancelled', 'unable_to_fulfil'], 'ready' => ['dispatched', 'completed', 'cancelled'], 'dispatched' => ['completed', 'unable_to_fulfil'], 'completed' => [], 'cancelled' => [], 'unable_to_fulfil' => []];

    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(self::STATUSES)], 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'page' => 'nullable|integer|min:1']);

        return response()->json(['data' => $this->filtered($request)->withCount('items')->latest()->paginate(30)->withQueryString()]);
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(self::STATUSES)], 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $audit->log($request, 'orders_exported', metadata: ['filters' => $request->only(['q', 'status', 'from', 'to'])]);

        return response()->streamDownload(function () use ($request): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Reference', 'Created', 'Customer', 'Mobile', 'City', 'Status', 'Prescription', 'Subtotal']);
            $this->filtered($request)->latest()->chunk(500, function ($orders) use ($stream): void {
                foreach ($orders as $order) {
                    fputcsv($stream, [$order->reference, $order->created_at->toIso8601String(), $order->customer_name, $order->mobile, $order->city, $order->status, $order->prescription_status, $order->subtotal]);
                }
            });
            fclose($stream);
        }, 'arkmed-orders-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function show(Order $order): JsonResponse
    {
        $data = $order->load(['items', 'statusHistory'])->toArray();
        $data['available_transitions'] = self::TRANSITIONS[$order->status] ?? [];

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, Order $order, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(self::STATUSES)], 'note' => 'nullable|string|max:1000']);
        $data['note'] = filled($data['note'] ?? null) ? trim($data['note']) : null;
        abort_if($data['status'] === $order->status, 422, 'Select a different status.');
        abort_unless(in_array($data['status'], self::TRANSITIONS[$order->status] ?? [], true), 422, "Order cannot move from {$order->status} to {$data['status']}.");
        $order->update(['status' => $data['status']]);
        $order->statusHistory()->create(['status' => $data['status'], 'note' => $data['note'], 'changed_by' => $request->user()->id]);
        $audit->log($request, 'order_status_updated', $order, ['status' => $data['status']]);

        $response = $order->fresh()->toArray();
        $response['available_transitions'] = self::TRANSITIONS[$order->status] ?? [];

        return response()->json(['data' => $response]);
    }

    private function filtered(Request $request)
    {
        $query = Order::query();
        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($builder) => $builder->where('reference', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%"));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        return $query;
    }
}
