<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrescriptionController extends Controller
{
    private const STATUSES = ['pending', 'reviewed', 'more_info_required', 'accepted', 'unable_to_fulfil', 'closed'];

    private const TRANSITIONS = ['pending' => ['reviewed', 'more_info_required', 'accepted', 'unable_to_fulfil'], 'reviewed' => ['more_info_required', 'accepted', 'unable_to_fulfil', 'closed'], 'more_info_required' => ['reviewed', 'accepted', 'unable_to_fulfil'], 'accepted' => ['closed'], 'unable_to_fulfil' => ['closed'], 'closed' => []];

    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(self::STATUSES)], 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'page' => 'nullable|integer|min:1']);
        $query = Prescription::with('order:id,reference,customer_name,mobile');
        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($builder) => $builder->where('public_id', 'like', "%{$search}%")->orWhereHas('order', fn ($order) => $order->where('reference', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%")));
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

        return response()->json(['data' => $query->latest()->paginate(30)->withQueryString()]);
    }

    public function show(Prescription $prescription): JsonResponse
    {
        $data = $prescription->load(['order', 'files'])->toArray();
        $data['available_transitions'] = self::TRANSITIONS[$prescription->status] ?? [];

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, Prescription $prescription, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(self::STATUSES)], 'review_note' => 'nullable|string|max:2000']);
        $data['review_note'] = filled($data['review_note'] ?? null) ? trim($data['review_note']) : null;
        if ($data['status'] !== $prescription->status) {
            abort_unless(in_array($data['status'], self::TRANSITIONS[$prescription->status] ?? [], true), 422, "Prescription cannot move from {$prescription->status} to {$data['status']}.");
        }
        if ($data['status'] === 'more_info_required') {
            abort_if(blank($data['review_note'] ?? null), 422, 'A review note is required when requesting more information.');
        }
        $data['reviewed_at'] = now();
        $prescription->update($data);
        $audit->log($request, 'prescription_review_updated', $prescription, ['status' => $data['status']]);

        $response = $prescription->fresh()->toArray();
        $response['available_transitions'] = self::TRANSITIONS[$prescription->status] ?? [];

        return response()->json(['data' => $response]);
    }
}
