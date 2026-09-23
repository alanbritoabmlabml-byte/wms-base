<?php

namespace App\Http\Middleware;

use App\Models\Warehouse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Almacén de trabajo del escritorio.
 *
 * Toda pantalla web opera sobre UN almacén (regla dura del doc 01: no existe
 * consulta de saldos sin warehouse_id). El almacén elegido vive en sesión y se
 * comparte con las vistas como $wh; si el usuario no eligió, se toma el primero
 * que tenga asignado en user_warehouse.
 */
class ResolveWorkingWarehouse
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $allowed = $user->warehouses()->orderBy('warehouses.code')->get();
            $current = $allowed->firstWhere('id', $request->session()->get('wms.warehouse_id'))
                ?? $allowed->first();

            if ($current) {
                $request->session()->put('wms.warehouse_id', $current->id);
            }

            $request->attributes->set('warehouse', $current);
            $request->attributes->set('role', $current ? $user->roleIn($current->id) : null);

            View::share([
                'wh' => $current,
                'whList' => $allowed,
                'whRole' => $current ? $user->roleIn($current->id) : null,
            ]);
        }

        return $next($request);
    }
}
