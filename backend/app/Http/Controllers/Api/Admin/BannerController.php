<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100', 'active' => 'nullable|boolean', 'page' => 'nullable|integer|min:1']);
        $query = Banner::query();
        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($builder) => $builder->where('title', 'like', "%{$search}%")->orWhere('subtitle', 'like', "%{$search}%"));
        }
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return response()->json(['data' => $query->orderBy('sort_order')->paginate(30)->withQueryString()]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $banner = Banner::create($this->validated($request));
        $audit->log($request, 'banner_created', $banner);

        return response()->json(['data' => $banner], 201);
    }

    public function update(Request $request, Banner $banner, AuditLogger $audit): JsonResponse
    {
        $banner->update($this->validated($request));
        $audit->log($request, 'banner_updated', $banner);

        return response()->json(['data' => $banner]);
    }

    public function destroy(Request $request, Banner $banner, AuditLogger $audit): JsonResponse
    {
        $audit->log($request, 'banner_deleted', $banner);
        $banner->delete();

        return response()->json(['message' => 'Banner deleted.']);
    }

    public function duplicate(Request $request, Banner $banner, AuditLogger $audit): JsonResponse
    {
        $copy = $banner->replicate();
        $copy->title = ($banner->title ?: 'Banner').' Copy';
        $copy->is_active = false;
        $copy->save();
        $audit->log($request, 'banner_duplicated', $copy, ['source_id' => $banner->id]);

        return response()->json(['data' => $copy], 201);
    }

    private function validated(Request $request): array
    {
        return $request->validate(['title' => 'nullable|string|max:255', 'subtitle' => 'nullable|string|max:500', 'desktop_image' => 'nullable|string|max:500', 'mobile_image' => 'nullable|string|max:500', 'cta_text' => 'nullable|string|max:100', 'cta_url' => 'nullable|string|max:500', 'sort_order' => 'required|integer|min:0', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'is_active' => 'required|boolean']);
    }
}
