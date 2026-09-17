<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:draft,published,scheduled', 'page' => 'nullable|integer|min:1']);
        $query = Page::query();
        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($builder) => $builder->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"));
        }
        match ($request->string('status')->value()) {
            'draft' => $query->where('is_active', false),
            'published' => $query->where('is_active', true)->where('published_at', '<=', now()),
            'scheduled' => $query->where('is_active', true)->where('published_at', '>', now()),
            default => null,
        };

        return response()->json(['data' => $query->latest()->paginate(30)->withQueryString()]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $page = Page::create($this->validated($request));
        $audit->log($request, 'page_created', $page);

        return response()->json(['data' => $page], 201);
    }

    public function update(Request $request, Page $page, AuditLogger $audit): JsonResponse
    {
        $page->update($this->validated($request, $page));
        $audit->log($request, 'page_updated', $page);

        return response()->json(['data' => $page]);
    }

    public function destroy(Request $request, Page $page, AuditLogger $audit): JsonResponse
    {
        $audit->log($request, 'page_deleted', $page);
        $page->delete();

        return response()->json(['message' => 'Page deleted.']);
    }

    public function duplicate(Request $request, Page $page, AuditLogger $audit): JsonResponse
    {
        $copy = $page->replicate();
        $copy->title .= ' Copy';
        $copy->slug .= '-copy-'.strtolower(str()->random(5));
        $copy->is_active = false;
        $copy->published_at = null;
        $copy->save();
        $audit->log($request, 'page_duplicated', $copy, ['source_id' => $page->id]);

        return response()->json(['data' => $copy], 201);
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        return $request->validate(['title' => 'required|string|max:255', 'slug' => ['required', 'string', 'max:255', Rule::unique('pages', 'slug')->ignore($page?->id)], 'body' => 'required|string|max:100000', 'seo_title' => 'nullable|string|max:255', 'seo_description' => 'nullable|string|max:500', 'is_active' => 'required|boolean', 'published_at' => 'nullable|date']);
    }
}
