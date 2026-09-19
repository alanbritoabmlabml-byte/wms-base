<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\KardexRequest;
use App\Http\Requests\StockByItemRequest;
use App\Http\Resources\KardexEntryResource;
use App\Http\Resources\StockByItemResource;
use App\Http\Resources\StockByLocationResource;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StockController extends Controller
{
    public function byLocation(Request $request, int $locationId): JsonResponse
    {
        $location = Location::with('zone')->findOrFail($locationId);

        $balances = StockBalance::query()
            ->with(['item.baseUom', 'lot'])
            ->where('location_id', $location->id)
            ->where('qty', '>', 0)
            ->get();

        return response()->json([
            'location' => [
                'id' => $location->id,
                'code' => $location->code,
                'zone' => $location->zone?->code,
            ],
            'data' => StockByLocationResource::collection($balances),
        ]);
    }

    /**
     * Ordenado FEFO: primero lo que vence antes, y a igualdad de vencimiento
     * por recorrido (sort_seq). El operador ve de donde DEBERIA sacar.
     */
    public function byItem(StockByItemRequest $request, int $itemId): JsonResponse
    {
        $balances = StockBalance::query()
            ->with(['location.zone', 'lot'])
            ->where('warehouse_id', $request->integer('warehouse_id'))
            ->where('item_id', $itemId)
            ->where('qty', '>', 0)
            ->get()
            ->sortBy([
                // Sin vencimiento va al final: no compite con lo que caduca.
                fn ($a, $b) => $this->expiryKey($a) <=> $this->expiryKey($b),
                fn ($a, $b) => ($a->location?->sort_seq ?? 0) <=> ($b->location?->sort_seq ?? 0),
            ])
            ->values();

        return response()->json([
            'data' => StockByItemResource::collection($balances),
        ]);
    }

    /** Kardex paginado con saldo corrido. */
    public function kardex(KardexRequest $request): JsonResponse
    {
        $itemId = $request->integer('item_id');
        $warehouseId = $request->integer('warehouse_id');
        $perPage = $request->integer('per_page') ?: 50;

        $base = StockMovement::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->when($request->filled('from'), fn ($q) => $q->where('occurred_at', '>=', Carbon::parse($request->input('from'))->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('occurred_at', '<=', Carbon::parse($request->input('to'))->endOfDay()));

        $page = (clone $base)
            ->with(['lot', 'fromLocation', 'toLocation', 'reasonCode', 'user'])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->paginate($perPage);

        // Saldo de arrastre: todo lo anterior a la primera fila de esta pagina.
        $opening = 0.0;

        if ($page->firstItem() !== null) {
            $firstId = $page->getCollection()->first()->id;

            $previous = (clone $base)
                ->where('id', '<', $firstId)
                ->get(['type', 'qty', 'from_location_id', 'to_location_id']);

            $opening = $previous->sum(fn ($m) => $this->signedQty($m));
        }

        $running = round((float) $opening, 4);

        $page->getCollection()->transform(function (StockMovement $movement) use (&$running) {
            $signed = $this->signedQty($movement);
            $running = round($running + $signed, 4);

            $movement->signed_qty = $signed;
            $movement->running_balance = $running;

            return $movement;
        });

        return response()->json([
            'opening_balance' => round((float) $opening, 4),
            'closing_balance' => $running,
            'data' => KardexEntryResource::collection($page->getCollection()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** Efecto neto del movimiento sobre el almacen (no sobre una ubicacion). */
    private function signedQty(StockMovement $movement): float
    {
        $in = $movement->to_location_id !== null ? (float) $movement->qty : 0.0;
        $out = $movement->from_location_id !== null ? (float) $movement->qty : 0.0;

        return round($in - $out, 4);
    }

    private function expiryKey(StockBalance $balance): string
    {
        return $balance->lot?->expires_at?->format('Y-m-d') ?? '9999-12-31';
    }
}
