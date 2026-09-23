<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureWarehouseAccess;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ResolveWorkingWarehouse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Escritorio web (Blade + Alpine) — sesiones y CSRF.
        web: __DIR__.'/../routes/web.php',
        // Contrato del colector bajo /api/v1 (ver docs/05-contrato-api.md).
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Detrás del balanceador de Render (HTTPS terminado en el proxy).
        $middleware->trustProxies(at: '*');
        // API-only: todas las respuestas son JSON, incluso los errores de framework.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        // Al entrar al escritorio se resuelve el almacén de trabajo de la sesión.
        $middleware->web(append: [
            ResolveWorkingWarehouse::class,
        ]);

        $middleware->alias([
            'warehouse.access' => EnsureWarehouseAccess::class,
            'role' => \App\Http\Middleware\EnsureWebRole::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // La API traduce cualquier excepción al formato de error del doc 05;
        // el escritorio conserva las páginas de error normales de Laravel.
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*')) {
                return ApiExceptionRenderer::render($e, $request);
            }

            return null;
        });
    })->create();
