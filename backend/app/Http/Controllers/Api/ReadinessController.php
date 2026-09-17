<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = ['database' => false, 'prescription_storage' => false, 'import_storage' => false];
        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (\Throwable) {
            // Reported below without exposing connection details.
        }
        $checks['prescription_storage'] = $this->writable('prescriptions');
        $checks['import_storage'] = $this->writable('imports');
        $ready = ! in_array(false, $checks, true);

        return response()->json(['data' => ['status' => $ready ? 'ready' : 'unavailable', 'checks' => $checks, 'time' => now()->toIso8601String()]], $ready ? 200 : 503);
    }

    private function writable(string $directory): bool
    {
        $path = storage_path('app/'.$directory);

        return is_dir($path) && is_writable($path);
    }
}
