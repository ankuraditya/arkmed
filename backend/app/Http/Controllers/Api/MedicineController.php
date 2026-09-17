<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MedicineResource;
use App\Models\Medicine;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|min:2|max:100', 'category' => 'nullable|string|max:100', 'prescription' => 'nullable|boolean', 'availability' => 'nullable|string|in:in_stock,low_stock,out_of_stock,on_request', 'sort' => 'nullable|string|in:featured,name,price_low,price_high', 'page' => 'nullable|integer|min:1|max:1000']);
        $query = Medicine::query()->with('category')->where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true));
        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('generic_name', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%")->orWhere('strength', 'like', "%{$search}%");
            });
        } if ($category = $request->string('category')->value()) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        } if ($request->has('prescription')) {
            $query->where('requires_prescription', $request->boolean('prescription'));
        } if ($availability = $request->string('availability')->value()) {
            $query->where('stock_status', $availability);
        }

        match ($request->string('sort')->value()) {
            'name' => $query->orderBy('name'),
            'price_low' => $query->orderByRaw('selling_price is null')->orderBy('selling_price')->orderBy('name'),
            'price_high' => $query->orderByDesc('selling_price')->orderBy('name'),
            default => $query->orderByDesc('is_featured')->orderBy('name'),
        };

        return MedicineResource::collection($query->paginate(24)->withQueryString());
    }

    public function show(Medicine $medicine): MedicineResource
    {
        abort_unless($medicine->is_active && $medicine->category()->where('is_active', true)->exists(), 404);

        return new MedicineResource($medicine->load('category'));
    }
}
