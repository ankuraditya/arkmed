<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Setting::orderBy('group')->orderBy('key')->get()]);
    }

    public function update(Request $request, AuditLogger $audit): JsonResponse
    {
        $rows = $request->validate(['settings' => 'required|array|max:100', 'settings.*.group' => 'required|string|max:50', 'settings.*.key' => 'required|string|max:100', 'settings.*.value' => 'nullable', 'settings.*.type' => 'required|in:string,boolean,integer,json', 'settings.*.is_public' => 'required|boolean'])['settings'];
        $identities = collect($rows)->map(fn ($row) => $row['group'].'.'.$row['key']);
        abort_if($identities->duplicates()->isNotEmpty(), 422, 'Each setting key may appear only once.');
        foreach ($rows as $row) {
            if ($row['type'] === 'boolean') {
                abort_unless(in_array((string) $row['value'], ['true', 'false', '1', '0'], true), 422, "{$row['key']} must be a boolean value.");
            }
            if ($row['type'] === 'integer') {
                abort_unless(filter_var($row['value'], FILTER_VALIDATE_INT) !== false, 422, "{$row['key']} must be an integer.");
            }
            if ($row['type'] === 'json') {
                json_decode((string) $row['value'], true);
                abort_if(json_last_error() !== JSON_ERROR_NONE, 422, "{$row['key']} must contain valid JSON.");
            }
            Setting::updateOrCreate(['group' => $row['group'], 'key' => $row['key']], $row);
        }
        $audit->log($request, 'settings_updated', metadata: ['keys' => $identities->values()->all()]);

        return response()->json(['data' => Setting::orderBy('group')->orderBy('key')->get(), 'message' => 'Settings updated.']);
    }
}
