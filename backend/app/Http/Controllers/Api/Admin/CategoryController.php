<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Category::withCount('medicines')->orderBy('sort_order')->get()]);
    }

    public function store(CategoryRequest $request, AuditLogger $audit): JsonResponse
    {
        $category = Category::create($request->validated());
        $audit->log($request, 'category_created', $category);

        return response()->json(['data' => $category], 201);
    }

    public function update(CategoryRequest $request, Category $category, AuditLogger $audit): JsonResponse
    {
        $category->update($request->validated());
        $audit->log($request, 'category_updated', $category);

        return response()->json(['data' => $category]);
    }

    public function destroy(Request $request, Category $category, AuditLogger $audit): JsonResponse
    {
        abort_if($category->medicines()->exists(), 422, 'A category containing medicines cannot be deleted.');
        $category->delete();
        $audit->log($request, 'category_deleted', $category);

        return response()->json(['message' => 'Category deleted.']);
    }
}
