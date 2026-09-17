<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePrescriptionUploadRequest;
use App\Models\TemporaryPrescriptionUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrescriptionUploadController extends Controller
{
    public function store(StorePrescriptionUploadRequest $request): JsonResponse
    {
        $files = collect($request->file('files'));
        abort_if($files->sum(fn ($file) => $file->getSize()) > 20 * 1024 * 1024, 422, 'Prescription uploads may total no more than 20 MB.');
        $checksums = $files->map(fn ($file) => hash_file('sha256', $file->getRealPath()));
        if ($checksums->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['files' => 'The same prescription file was selected more than once.']);
        }
        $uploads = $files->map(function ($file) {
            $token = (string) Str::uuid();
            $path = $file->storeAs('temporary/'.now()->format('Y/m/d'), $token.'.'.$file->extension(), 'prescriptions');
            $upload = TemporaryPrescriptionUpload::create(['token' => $token, 'disk' => 'prescriptions', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath()), 'expires_at' => now()->addHours(24)]);

            return ['token' => $upload->token, 'name' => $upload->original_name, 'expires_at' => $upload->expires_at->toIso8601String()];
        });

        return response()->json(['data' => $uploads, 'message' => 'Prescription files uploaded securely.'], 201);
    }
}
