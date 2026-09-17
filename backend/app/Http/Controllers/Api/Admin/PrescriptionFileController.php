<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrescriptionFile;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrescriptionFileController extends Controller
{
    public function __invoke(Request $request, PrescriptionFile $file, AuditLogger $audit): StreamedResponse
    {
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);
        $audit->log($request, 'prescription_file_accessed', $file, ['prescription_id' => $file->prescription_id]);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Content-Type' => $file->mime_type, 'Cache-Control' => 'no-store, private']);
    }
}
