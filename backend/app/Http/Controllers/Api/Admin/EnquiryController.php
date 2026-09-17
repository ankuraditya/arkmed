<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnquiryController extends Controller
{
    private const TRANSITIONS = ['new' => ['contacted', 'closed'], 'contacted' => ['closed'], 'closed' => []];

    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100', 'type' => 'nullable|string|in:doctor_consultation,health_checkup,contact', 'status' => 'nullable|string|in:new,contacted,closed', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'page' => 'nullable|integer|min:1']);
        $query = Enquiry::latest();
        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        return response()->json(['data' => $query->paginate(30)]);
    }

    public function show(Enquiry $enquiry): JsonResponse
    {
        $data = $enquiry->toArray();
        $data['available_transitions'] = self::TRANSITIONS[$enquiry->status] ?? [];

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, Enquiry $enquiry, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'contacted', 'closed'])]]);
        abort_if($data['status'] === $enquiry->status, 422, 'Select a different status.');
        abort_unless(in_array($data['status'], self::TRANSITIONS[$enquiry->status] ?? [], true), 422, "Enquiry cannot move from {$enquiry->status} to {$data['status']}.");
        $enquiry->update($data);
        $audit->log($request, 'enquiry_status_updated', $enquiry, $data);

        $response = $enquiry->fresh()->toArray();
        $response['available_transitions'] = self::TRANSITIONS[$enquiry->status] ?? [];

        return response()->json(['data' => $response]);
    }
}
