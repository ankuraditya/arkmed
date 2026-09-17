<?php

use App\Http\Middleware\EnsureActiveAdmin;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$apiPrefix = env('API_ROUTE_PREFIX', 'api');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: $apiPrefix,
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['active.admin' => EnsureActiveAdmin::class, 'role' => RequireRole::class]);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($apiPrefix): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is(trim($apiPrefix.'/*', '/')) || $request->expectsJson(),
        );
    })->create();
