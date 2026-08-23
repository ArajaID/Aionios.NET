<?php

use App\Http\Middleware\AddRequestId;
use App\Http\Middleware\EnsureApiPermission;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoStoreApiResponse;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'api.permission' => EnsureApiPermission::class,
            'api.idempotent' => EnsureIdempotency::class,
        ]);
        $middleware->api(append: [
            AddRequestId::class,
            NoStoreApiResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'error' => ['code' => 'VALIDATION_ERROR', 'fields' => $e->errors()],
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Authentication is required.',
                'error' => ['code' => 'AUTH_UNAUTHORIZED'],
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
                'error' => ['code' => 'FORBIDDEN'],
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
                'error' => ['code' => 'RESOURCE_NOT_FOUND'],
            ], 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Too many requests.',
                'error' => ['code' => 'RATE_LIMIT_EXCEEDED'],
            ], 429);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/v1/*') || ! app()->isProduction()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 500);
        });
    })->create();
