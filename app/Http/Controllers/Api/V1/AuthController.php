<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Support\ApiResponse;
use App\Support\RolePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @tags Autentikasi (Authentication)
 */
class AuthController extends Controller
{
    /**
     * Login Akun & Dapatkan Token Akses
     *
     * Mengotentikasi pengguna berdasarkan email dan password untuk mendapatkan Sanctum Bearer Token.
     * Token yang diberikan menyertakan abilities/permissions sesuai role pengguna (Owner, Admin Keuangan, Admin Jaringan).
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->lower()->toString())->first();

        if (! $user || ! $user->is_active || ! Hash::check($request->string('password')->toString(), $user->password)) {
            AuditLog::create([
                'user_id' => $user?->id,
                'action' => 'api_login_failed',
                'module' => 'authentication',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'source' => 'MOBILE',
                'request_id' => $request->attributes->get('request_id'),
            ]);

            return ApiResponse::error('Email atau password tidak valid.', 'AUTH_INVALID_CREDENTIALS', 401);
        }

        $permissions = RolePermissions::forRole($user->role);
        $token = $user->createToken($request->string('device_name')->toString(), $permissions);
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'api_login',
            'module' => 'authentication',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'new_values' => ['device_name' => $request->string('device_name')->toString()],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'source' => 'MOBILE',
            'request_id' => $request->attributes->get('request_id'),
        ]);

        return ApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], 'Login successful.');
    }

    /**
     * Logout Sesi Saat Ini
     *
     * Mencabut (revoke) personal access token yang sedang digunakan pada sesi aktif saat ini.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        AuditService::log('api_logout', 'authentication', 'User', $request->user()->id);
        PersonalAccessToken::findToken((string) $request->bearerToken())?->delete();
        Auth::forgetGuards();

        return ApiResponse::success(null, 'Logout successful.');
    }

    /**
     * Logout Semua Perangkat
     *
     * Mencabut (revoke) seluruh token akses aktif pengguna di semua perangkat mobile maupun web.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logoutAll(Request $request): JsonResponse
    {
        AuditService::log('api_logout_all', 'authentication', 'User', $request->user()->id);
        $request->user()->tokens()->delete();
        Auth::forgetGuards();

        return ApiResponse::success(null, 'All device sessions have been revoked.');
    }

    /**
     * Profil Pengguna Aktif
     *
     * Mengambil informasi data profil, role operasional, dan daftar kemampuan/permissions dari pengguna yang sedang login.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success($this->userPayload($request->user()));
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'permissions' => RolePermissions::forRole($user->role),
        ];
    }
}
