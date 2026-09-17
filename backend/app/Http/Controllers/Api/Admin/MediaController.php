<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $query = Media::query();
        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($builder) => $builder->where('original_name', 'like', "%{$search}%")->orWhere('alt_text', 'like', "%{$search}%"));
        }

        return response()->json(['data' => $query->latest()->paginate(30)->withQueryString()]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['file' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120', 'alt_text' => 'required|string|max:255']);
        $file = $data['file'];
        $path = $file->storeAs('cms/'.now()->format('Y/m'), Str::uuid().'.'.$file->extension(), 'public');
        $media = Media::create(['disk' => 'public', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'alt_text' => $data['alt_text'], 'uploaded_by' => $request->user()->id]);
        $audit->log($request, 'media_uploaded', $media);

        return response()->json(['data' => $media], 201);
    }

    public function destroy(Request $request, Media $medium, AuditLogger $audit): JsonResponse
    {
        Storage::disk($medium->disk)->delete($medium->path);
        $audit->log($request, 'media_deleted', $medium);
        $medium->delete();

        return response()->json(['message' => 'Media deleted.']);
    }

    public function update(Request $request, Media $medium, AuditLogger $audit): JsonResponse
    {
        $medium->update($request->validate(['alt_text' => 'required|string|max:255']));
        $audit->log($request, 'media_updated', $medium);

        return response()->json(['data' => $medium]);
    }
}
