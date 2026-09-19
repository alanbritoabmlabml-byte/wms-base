<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureWarehouseAccess;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Todo el contrato vive bajo /api/v1 (ver docs/05-contrato-api.md).
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API-only: todas las respuestas son JSON, incluso los errores de framework.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'warehouse.access' => EnsureWarehouseAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Traduce cualquier excepcion al formato de error del doc 05.
        $exceptions->render(function (Throwable $e, $request) {
            return ApiExceptionRenderer::render($e, $request);
        });
    })->create();
