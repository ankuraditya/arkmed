<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MedicineRequest;
use App\Models\Medicine;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MedicineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|integer|exists:categories,id', 'stock_status' => 'nullable|in:in_stock,low_stock,out_of_stock,on_request', 'active' => 'nullable|boolean', 'prescription' => 'nullable|boolean', 'featured' => 'nullable|boolean', 'archived' => 'nullable|boolean', 'page' => 'nullable|integer|min:1']);
        $query = Medicine::query()->with('category');
        if ($request->boolean('archived')) {
            $query->onlyTrashed();
        }
        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")->orWhere('generic_name', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%"));
        }
        foreach (['category_id' => 'category', 'stock_status' => 'stock_status'] as $column => $key) {
            if ($request->filled($key)) {
                $query->where($column, $request->input($key));
            }
        }
        foreach (['is_active' => 'active', 'requires_prescription' => 'prescription', 'is_featured' => 'featured'] as $column => $key) {
            if ($request->has($key)) {
                $query->where($column, $request->boolean($key));
            }
        }

        return response()->json(['data' => $query->latest()->paginate(30)->withQueryString()]);
    }

    public function store(MedicineRequest $request, AuditLogger $audit): JsonResponse
    {
        $medicine = Medicine::create($request->validated() + ['public_id' => (string) Str::ulid()]);
        $audit->log($request, 'medicine_created', $medicine);

        return response()->json(['data' => $medicine->load('category')], 201);
    }

    public function show(Medicine $medicine): JsonResponse
    {
        return response()->json(['data' => $medicine->load('category')]);
    }

    public function update(MedicineRequest $request, Medicine $medicine, AuditLogger $audit): JsonResponse
    {
        $medicine->update($request->validated());
        $audit->log($request, 'medicine_updated', $medicine);

        return response()->json(['data' => $medicine->load('category')]);
    }

    public function destroy(Request $request, Medicine $medicine, AuditLogger $audit): JsonResponse
    {
        $medicine->delete();
        $audit->log($request, 'medicine_archived', $medicine);

        return response()->json(['message' => 'Medicine archived.']);
    }

    public function restore(Request $request, int $id, AuditLogger $audit): JsonResponse
    {
        $medicine = Medicine::onlyTrashed()->findOrFail($id);
        $medicine->restore();
        $audit->log($request, 'medicine_restored', $medicine);

        return response()->json(['data' => $medicine->load('category')]);
    }

    public function duplicate(Request $request, Medicine $medicine, AuditLogger $audit): JsonResponse
    {
        $copy = $medicine->replicate(['sku']);
        $copy->public_id = (string) Str::ulid();
        $copy->name = $medicine->name.' Copy';
        $copy->slug = $medicine->slug.'-copy-'.Str::lower(Str::random(5));
        $copy->is_active = false;
        $copy->is_featured = false;
        $copy->save();
        $audit->log($request, 'medicine_duplicated', $copy, ['source_id' => $medicine->id]);

        return response()->json(['data' => $copy->load('category')], 201);
    }

    public function bulk(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'integer|exists:medicines,id', 'action' => ['required', Rule::in(['activate', 'deactivate', 'in_stock', 'out_of_stock', 'on_request'])]]);
        $updates = match ($data['action']) {
            'activate' => ['is_active' => true],
            'deactivate' => ['is_active' => false],
            default => ['stock_status' => $data['action']],
        };
        $count = Medicine::whereIn('id', $data['ids'])->update($updates);
        $audit->log($request, 'medicines_bulk_updated', metadata: ['ids' => $data['ids'], 'action' => $data['action'], 'count' => $count]);

        return response()->json(['data' => ['updated' => $count]]);
    }
}
