<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(AdminLoginRequest $request, AuditLogger $audit): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();
        if (! $user || ! $user->is_active || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => 'The credentials are invalid or the account is inactive.']);
        }$user->update(['last_login_at' => now()]);
        $token = $user->createToken('admin-panel', [$user->role], now()->addHours(12))->plainTextToken;
        $audit->log($request, 'admin_logged_in', $user);

        return response()->json(['data' => ['token' => $token, 'user' => $this->userData($user)]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userData($request->user())]);
    }

    public function logout(Request $request, AuditLogger $audit): JsonResponse
    {
        $audit->log($request, 'admin_logged_out', $request->user());
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function updateProfile(Request $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)]]);
        $user->update($data);
        $audit->log($request, 'admin_profile_updated', $user);

        return response()->json(['data' => $this->userData($user)]);
    }

    public function updatePassword(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['current_password' => 'required|string', 'password' => 'required|string|min:12|confirmed']);
        abort_unless(Hash::check($data['current_password'], $request->user()->password), 422, 'The current password is incorrect.');
        $user = $request->user();
        $user->update(['password' => $data['password']]);
        $audit->log($request, 'admin_password_changed', $user);
        $user->tokens()->delete();

        return response()->json(['message' => 'Password changed. Sign in again with your new password.']);
    }

    private function userData(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role];
    }
}
