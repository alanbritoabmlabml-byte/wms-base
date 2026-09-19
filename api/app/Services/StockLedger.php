<?php

namespace App\Services;

use App\Data\MovementData;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\InvalidMovementException;
use App\Exceptions\Domain\LocationInactiveException;
use App\Exceptions\Domain\LocationNotInWarehouseException;
use App\Exceptions\Domain\MixingNotAllowedException;
use App\Exceptions\Domain\ReasonCodeRequiredException;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * El corazon del WMS: el unico punto por donde pasa TODO movimiento de stock.
 *
 * Nadie -- ni el seeder, ni un controlador, ni un comando -- escribe
 * `stock_balances` directamente. Si lo hace, el kardex deja de cuadrar y se
 * pierde la capacidad de auditar diferencias contra WorkCorp/SIMEC.
 */
class StockLedger
{
    /** Tolerancia para comparaciones de decimal(18,4). */
    private const EPSILON = 0.00005;

    /**
     * Postea un movimiento y actualiza los saldos, todo dentro de UNA transaccion.
     *
     * Si el `client_uuid` ya existe devuelve el movimiento original sin tocar
     * saldos: es lo que hace segura la cola offline del colector.
     */
    public function post(MovementData $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            // 1. Idempotencia. El colector reintenta; el servidor no duplica.
            $existing = StockMovement::where('client_uuid', $data->clientUuid)->first();

            if ($existing) {
                return $existing;
            }

            $this->assertQtyIsPositive($data);
            $this->assertReasonWhenRequired($data);

            $warehouse = Warehouse::findOrFail($data->warehouseId);

            $fromLocation = $this->resolveLocation($data->fromLocationId, $warehouse->id);
            $toLocation = $this->resolveLocation($data->toLocationId, $warehouse->id);

            if (! $fromLocation && ! $toLocation) {
                throw new InvalidMovementException(
                    'Un movimiento necesita al menos una ubicacion (origen o destino)',
                    ['type' => $data->type],
                );
            }

            $fromStatus = $data->fromStatus ?? $data->toStatus ?? 'BUENO';
            $toStatus = $data->toStatus ?? $data->fromStatus ?? 'BUENO';

            // 2. Bloqueo pesimista de las filas de saldo implicadas, SIEMPRE en
            //    orden ascendente de id: dos colectores que tocan las mismas dos
            //    ubicaciones en sentido inverso no se bloquean mutuamente.
            $keys = [];

            if ($fromLocation) {
                $keys[] = $this->balanceKey($data, $fromLocation->id, $fromStatus);
            }

            if ($toLocation) {
                $keys[] = $this->balanceKey($data, $toLocation->id, $toStatus);
            }

            $balances = $this->lockBalances($keys);

            // 3. Validaciones de negocio, ya con las filas bloqueadas.
            if ($toLocation) {
                $this->assertMixingAllowed($toLocation, $data->itemId);
            }

            if ($fromLocation) {
                $source = $balances[$this->keyHash($data->warehouseId, $fromLocation->id, $data->itemId, $data->lotId, $fromStatus)];

                if (! $warehouse->allows_negative_stock && ((float) $source->qty) - $data->qty < -self::EPSILON) {
                    throw new InsufficientStockException(
                        $fromLocation->code,
                        round((float) $source->qty, 4),
                        round($data->qty, 4),
                    );
                }
            }

            // 4. El asiento del kardex (append-only).
            $movement = StockMovement::create(array_merge($data->toMovementAttributes(), [
                'uuid' => (string) Str::uuid(),
                'from_status' => $fromLocation ? $fromStatus : null,
                'to_status' => $toLocation ? $toStatus : null,
            ]));

            // 5. Proyeccion: resta en origen, suma en destino.
            $now = Carbon::now();

            if ($fromLocation) {
                $this->applyDelta(
                    $balances[$this->keyHash($data->warehouseId, $fromLocation->id, $data->itemId, $data->lotId, $fromStatus)],
                    -$data->qty,
                    $now,
                );
            }

            if ($toLocation) {
                $this->applyDelta(
                    $balances[$this->keyHash($data->warehouseId, $toLocation->id, $data->itemId, $data->lotId, $toStatus)],
                    $data->qty,
                    $now,
                );
            }

            return $movement;
        });
    }

    /**
     * Recalcula un saldo sumando el kardex. Es la herramienta de auditoria:
     * si esto no coincide con `stock_balances`, hay algo que escribio saldos
     * por fuera del ledger.
     *
     * @param  bool  $persist  true = ademas corrige la fila de saldo.
     */
    public function rebuildBalance(
        int $warehouseId,
        int $locationId,
        int $itemId,
        int $lotId,
        string $status = 'BUENO',
        bool $persist = false,
    ): float {
        $in = (float) StockMovement::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->where('lot_id', $lotId)
            ->where('to_location_id', $locationId)
            ->where('to_status', $status)
            ->sum('qty');

        $out = (float) StockMovement::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->where('lot_id', $lotId)
            ->where('from_location_id', $locationId)
            ->where('from_status', $status)
            ->sum('qty');

        $qty = round($in - $out, 4);

        if ($persist) {
            StockBalance::updateOrCreate(
                [
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'item_id' => $itemId,
                    'lot_id' => $lotId,
                    'status' => $status,
                ],
                ['qty' => $qty],
            );
        }

        return $qty;
    }

    /** Saldo actual proyectado de una clave (o 0 si la fila no existe). */
    public function currentQty(int $warehouseId, int $locationId, int $itemId, int $lotId, string $status = 'BUENO'): float
    {
        return (float) StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('item_id', $itemId)
            ->where('lot_id', $lotId)
            ->where('status', $status)
            ->value('qty') ?? 0.0;
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    private function assertQtyIsPositive(MovementData $data): void
    {
        if ($data->qty <= 0) {
            throw new InvalidMovementException(
                'La cantidad debe ser mayor a cero; el sentido lo da from/to',
                ['qty' => $data->qty],
            );
        }
    }

    private function assertReasonWhenRequired(MovementData $data): void
    {
        if (in_array($data->type, StockMovement::REASON_REQUIRED_TYPES, true) && ! $data->reasonCodeId) {
            throw new ReasonCodeRequiredException($data->type);
        }
    }

    /** La ubicacion debe pertenecer al almacen del movimiento y estar activa. */
    private function resolveLocation(?int $locationId, int $warehouseId): ?Location
    {
        if (! $locationId) {
            return null;
        }

        $location = Location::find($locationId);

        if (! $location || $location->warehouse_id !== $warehouseId) {
            throw new LocationNotInWarehouseException($locationId, $warehouseId);
        }

        if (! $location->is_active) {
            throw new LocationInactiveException($location->code);
        }

        return $location;
    }

    /**
     * Una ubicacion con `is_mixing_allowed = false` solo admite un item a la vez.
     */
    private function assertMixingAllowed(Location $location, int $itemId): void
    {
        if ($location->is_mixing_allowed) {
            return;
        }

        $otherItemId = StockBalance::query()
            ->where('location_id', $location->id)
            ->where('item_id', '!=', $itemId)
            ->where('qty', '>', 0)
            ->value('item_id');

        if ($otherItemId) {
            throw new MixingNotAllowedException($location->code, (int) $otherItemId);
        }
    }

    private function balanceKey(MovementData $data, int $locationId, string $status): array
    {
        return [
            'warehouse_id' => $data->warehouseId,
            'location_id' => $locationId,
            'item_id' => $data->itemId,
            'lot_id' => $data->lotId,
            'status' => $status,
        ];
    }

    private function keyHash(int $warehouseId, int $locationId, int $itemId, int $lotId, string $status): string
    {
        return implode('|', [$warehouseId, $locationId, $itemId, $lotId, $status]);
    }

    /**
     * Garantiza que exista la fila de saldo de cada clave y las bloquea con
     * lockForUpdate() en orden determinista por id.
     *
     * @param  array<int,array<string,mixed>>  $keys
     * @return array<string,StockBalance>
     */
    private function lockBalances(array $keys): array
    {
        $now = Carbon::now();

        // insertOrIgnore respeta el UNIQUE de 5 columnas: si otro colector la creo
        // primero, no pasa nada, la fila ya esta.
        foreach ($keys as $key) {
            StockBalance::insertOrIgnore(array_merge($key, [
                'qty' => 0,
                'qty_allocated' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $ids = [];

        foreach ($keys as $key) {
            $ids[] = (int) StockBalance::query()->where($key)->value('id');
        }

        sort($ids);

        $locked = StockBalance::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $byHash = [];

        foreach ($locked as $balance) {
            $byHash[$this->keyHash(
                (int) $balance->warehouse_id,
                (int) $balance->location_id,
                (int) $balance->item_id,
                (int) $balance->lot_id,
                (string) $balance->status,
            )] = $balance;
        }

        return $byHash;
    }

    private function applyDelta(StockBalance $balance, float $delta, Carbon $now): void
    {
        // Update atomico sobre la fila ya bloqueada. qty_available es columna
        // generada: no se toca nunca.
        StockBalance::query()
            ->whereKey($balance->getKey())
            ->update([
                'qty' => DB::raw('qty + ('.$this->sqlNumber($delta).')'),
                'last_movement_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function sqlNumber(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
