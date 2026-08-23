<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\RolePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! RolePermissions::allows($user->role, $permission) || ! $user->tokenCan($permission)) {
            return ApiResponse::error(
                'You do not have permission to perform this action.',
                'FORBIDDEN',
                403,
            );
        }

        return $next($request);
    }
}
