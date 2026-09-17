<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100', 'action' => 'nullable|string|max:100', 'user_id' => 'nullable|integer|exists:users,id', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'page' => 'nullable|integer|min:1']);

        return response()->json(['data' => $this->filtered($request)->with('user:id,name,email')->latest()->paginate(50)->withQueryString()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate(['q' => 'nullable|string|max:100', 'action' => 'nullable|string|max:100', 'user_id' => 'nullable|integer|exists:users,id', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);

        return response()->streamDownload(function () use ($request): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Timestamp', 'Action', 'User', 'Subject type', 'Subject ID', 'IP address']);
            $this->filtered($request)->with('user:id,name,email')->latest()->each(function (AuditLog $log) use ($stream): void {
                fputcsv($stream, [$log->created_at->toIso8601String(), $log->action, $log->user?->email ?? 'System', $log->auditable_type, $log->auditable_id, $log->ip_address]);
            });
            fclose($stream);
        }, 'arkmed-audit-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request)
    {
        $query = AuditLog::query();
        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($builder) => $builder->where('action', 'like', "%{$search}%")->orWhere('auditable_type', 'like', "%{$search}%")->orWhere('ip_address', 'like', "%{$search}%")->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->value());
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        return $query;
    }
}
