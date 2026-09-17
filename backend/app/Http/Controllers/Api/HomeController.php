<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MedicineResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Medicine;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $now = now();
        $banners = Banner::where('is_active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))->orderBy('sort_order')->get();
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'description']);
        $featured = Medicine::with('category')->where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true))->where('is_featured', true)->limit(8)->get();

        return response()->json(['data' => ['banners' => $banners, 'categories' => $categories, 'featured_medicines' => MedicineResource::collection($featured)]]);
    }
}
