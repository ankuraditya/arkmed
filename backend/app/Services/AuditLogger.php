<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(Request $request, string $action, ?Model $subject = null, array $metadata = []): void
    {
        AuditLog::create(['user_id' => $request->user()?->id, 'action' => $action, 'auditable_type' => $subject?->getMorphClass(), 'auditable_id' => $subject?->getKey(), 'metadata_json' => $metadata ?: null, 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000)]);
    }
}
