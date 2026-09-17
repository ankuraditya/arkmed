<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    private const ROLES = ['super_admin', 'pharmacy_admin', 'content_manager', 'order_staff', 'prescription_reviewer'];

    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100', 'role' => ['nullable', Rule::in(self::ROLES)], 'active' => 'nullable|boolean', 'page' => 'nullable|integer|min:1']);
        $query = User::query()->select(['id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at']);
        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if ($request->filled('role')) {
            $query->where('role', $request->string('role')->value());
        }
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return response()->json(['data' => $query->latest()->paginate(30)->withQueryString()]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12', 'role' => ['required', Rule::in(self::ROLES)], 'is_active' => 'required|boolean']);
        $user = User::create($data);
        $audit->log($request, 'admin_user_created', $user, ['role' => $user->role]);

        return response()->json(['data' => $user->only(['id', 'name', 'email', 'role', 'is_active'])], 201);
    }

    public function update(Request $request, User $user, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)], 'password' => 'nullable|string|min:12', 'role' => ['required', Rule::in(self::ROLES)], 'is_active' => 'required|boolean']);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        abort_if($request->user()->is($user) && (! ($data['is_active'] ?? true) || ($data['role'] ?? $user->role) !== 'super_admin'), 422, 'You cannot remove your own super-admin access.');
        $removingSuperAdmin = $user->role === 'super_admin' && (($data['role'] ?? null) !== 'super_admin' || ! ($data['is_active'] ?? true));
        abort_if($removingSuperAdmin && User::where('role', 'super_admin')->where('is_active', true)->count() <= 1, 422, 'At least one active super administrator is required.');
        $securityChanged = isset($data['password']) || $user->role !== $data['role'] || $user->is_active !== $data['is_active'];
        $user->update($data);
        if ($securityChanged) {
            $user->tokens()->delete();
        }
        $audit->log($request, 'admin_user_updated', $user, ['role' => $user->role, 'is_active' => $user->is_active]);

        return response()->json(['data' => $user->only(['id', 'name', 'email', 'role', 'is_active'])]);
    }
}
