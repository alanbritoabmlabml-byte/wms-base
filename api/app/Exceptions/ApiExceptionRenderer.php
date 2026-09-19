<?php

namespace App\Exceptions;

use App\Exceptions\Domain\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Traduce cualquier excepcion al formato de error del doc 05:
 *
 *   { "error": { "code": "...", "message": "...", "details": { ... } } }
 */
class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        // 1. Errores de dominio: ya traen su propio code y status.
        if ($e instanceof DomainException) {
            return response()->json($e->toArray(), $e->status);
        }

        // 2. Validacion de Form Requests.
        if ($e instanceof ValidationException) {
            return self::error(
                'VALIDATION_FAILED',
                'Los datos enviados no son validos',
                422,
                ['fields' => $e->errors()],
            );
        }

        if ($e instanceof AuthenticationException) {
            return self::error('UNAUTHENTICATED', 'Token ausente o invalido', 401);
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return self::error('FORBIDDEN', $e->getMessage() ?: 'Acceso denegado', 403);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return self::error('NOT_FOUND', 'Recurso no encontrado', 404);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return self::error(
                match ($status) {
                    401 => 'UNAUTHENTICATED',
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    422 => 'VALIDATION_FAILED',
                    429 => 'TOO_MANY_REQUESTS',
                    default => 'HTTP_ERROR',
                },
                $e->getMessage() ?: 'Error HTTP '.$status,
                $status,
            );
        }

        // 3. Cualquier otra cosa: 500 sin filtrar detalles internos en produccion.
        return self::error(
            'SERVER_ERROR',
            config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
            500,
            config('app.debug') ? ['exception' => $e::class] : [],
        );
    }

    private static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        $payload = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $payload['details'] = $details;
        }

        return response()->json(['error' => $payload], $status);
    }
}
