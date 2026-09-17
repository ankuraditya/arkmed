<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->where('is_active', true)->whereNotNull('published_at')->where('published_at', '<=', now())->firstOrFail();

        return response()->json(['data' => $page->only(['title', 'slug', 'body', 'seo_title', 'seo_description', 'published_at'])]);
    }
}
