<?php

namespace App\Http\Middleware;

use App\Models\Location;
use App\Models\Receipt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un operador NUNCA puede leer ni escribir en un almacen que no tiene asignado
 * en `user_warehouse`. Este middleware resuelve el almacen implicado en la
 * peticion (query, body o recurso de la ruta) y corta con 403 si no corresponde.
 */
class EnsureWarehouseAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $warehouseId = $this->resolveWarehouseId($request);

        // Sin almacen identificable no hay nada que autorizar aqui: el Form Request
        // correspondiente se encarga de exigirlo.
        if ($warehouseId === null) {
            return $next($request);
        }

        $allowed = $user->warehouses()->where('warehouses.id', $warehouseId)->exists();

        if (! $allowed) {
            abort(403, 'El usuario no tiene asignado el almacen '.$warehouseId);
        }

        $request->attributes->set('warehouse_id', $warehouseId);

        return $next($request);
    }

    private function resolveWarehouseId(Request $request): ?int
    {
        // 1. Explicito en query string o cuerpo.
        $explicit = $request->input('warehouse_id', $request->query('warehouse_id'));
        if (is_numeric($explicit)) {
            return (int) $explicit;
        }

        // 2. Derivado del recurso de la ruta.
        if ($locationId = $request->route('locationId')) {
            return Location::whereKey($locationId)->value('warehouse_id');
        }

        if ($receiptId = $request->route('receipt')) {
            $receiptId = $receiptId instanceof Receipt ? $receiptId->getKey() : $receiptId;

            return Receipt::whereKey($receiptId)->value('warehouse_id');
        }

        // 3. Derivado de la ubicacion destino/origen de un movimiento suelto.
        foreach (['to_location_id', 'from_location_id', 'location_id'] as $field) {
            if ($value = $request->input($field)) {
                return Location::whereKey($value)->value('warehouse_id');
            }
        }

        return null;
    }
}
