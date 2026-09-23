<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta web a ciertos roles dentro del almacén de trabajo.
 * Uso: ->middleware('role:ADMIN,SUPERVISOR')
 */
class EnsureWebRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->attributes->get('role');

        if (! $role || ! in_array($role, $roles, true)) {
            abort(403, 'No tienes permiso para esta acción en el almacén de trabajo.');
        }

        return $next($request);
    }
}
