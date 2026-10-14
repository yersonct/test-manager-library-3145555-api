<?php

use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/health',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'token' => AuthenticateToken::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (QueryException $exception): void {
            Log::error('A database operation failed.', [
                'sql_state' => $exception->errorInfo[0] ?? 'unknown',
            ]);
        })->stop();
        $exceptions->render(function (Throwable $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return App\Support\ApiExceptionRenderer::render($exception);
        });
    })
    ->create();
