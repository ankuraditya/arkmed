<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Services\AuditLogger;
use App\Services\MedicineCsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicineImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:pending,previewed,invalid,completed', 'page' => 'nullable|integer|min:1']);
        $query = ImportJob::withCount('errors');
        if ($search = $request->string('q')->trim()->value()) {
            $query->where('file_name', 'like', "%{$search}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return response()->json(['data' => $query->latest()->paginate(20)->withQueryString()]);
    }

    public function show(string $publicId): JsonResponse
    {
        $job = ImportJob::where('public_id', $publicId)->with('errors')->firstOrFail();

        return response()->json(['data' => $job]);
    }

    public function errors(string $publicId): StreamedResponse
    {
        $job = ImportJob::where('public_id', $publicId)->with('errors')->firstOrFail();

        return response()->streamDownload(function () use ($job): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Row', 'Error', 'Source data']);
            foreach ($job->errors as $error) {
                fputcsv($stream, [$error->row_number, $error->error_message, json_encode($error->raw_json)]);
            }
            fclose($stream);
        }, 'arkmed-import-errors-'.$job->public_id.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function template()
    {
        $line = implode(',', MedicineCsvImportService::HEADERS)."\n".',Example Medicine,Tablets,,,,,500 mg,Tablet,,,,on_request,0,0'."\n";

        return response()->streamDownload(fn () => print ($line), 'arkmed-medicine-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function preview(Request $request, MedicineCsvImportService $service, AuditLogger $audit): JsonResponse
    {
        $file = $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120'])['file'];
        $publicId = (string) Str::uuid();
        $path = $file->storeAs('medicine-imports', $publicId.'.csv', 'imports');
        $job = ImportJob::create(['public_id' => $publicId, 'file_name' => $file->getClientOriginalName(), 'stored_path' => $path, 'created_by' => $request->user()->id]);
        $service->preview($job);
        $audit->log($request, 'medicine_import_previewed', $job);

        return response()->json(['data' => $job->load('errors')], 201);
    }

    public function commit(Request $request, string $publicId, MedicineCsvImportService $service, AuditLogger $audit): JsonResponse
    {
        $job = ImportJob::where('public_id', $publicId)->firstOrFail();
        $service->commit($job);
        $audit->log($request, 'medicine_import_committed', $job, ['success_rows' => $job->success_rows]);

        return response()->json(['data' => $job, 'message' => 'Verified rows imported as inactive medicines.']);
    }
}
