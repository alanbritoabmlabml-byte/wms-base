<?php

namespace App\Http\Controllers\Web;

use App\Data\MovementData;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Dispatch;
use App\Models\Driver;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Setting;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\StockLedger;
use App\Support\NavCounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pedidos de venta y despacho: tablero kanban, olas de picking con reserva
 * FEFO/FIFO, avance de estado y armado de despachos (camión + chofer).
 *
 * El descuento de stock ocurre UNA sola vez, al pasar a DESPACHADO, vía
 * StockLedger (movimiento DESPACHO por cada línea reservada).
 */
class OutboundController extends Controller
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function board(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');

        $orders = SalesOrder::with(['customer', 'assignee'])->withCount('lines')
            ->forWarehouse($wh->id)
            ->where(fn ($q) => $q->open()->orWhere(fn ($x) => $x->where('status', 'DESPACHADO')->where('dispatched_at', '>=', now()->subDay())))
            ->orderByRaw("case when priority = 'URGENTE' then 0 else 1 end")->orderBy('ordered_at')->orderBy('id')
            ->get();

        $columns = [];
        foreach (SalesOrder::STATUSES as $st) {
            $columns[$st] = $orders->where('status', $st)->values();
        }

        return view('outbound.board', [
            'title' => 'Pedidos y despacho',
            'columns' => $columns,
            'labels' => SalesOrder::STATUS_LABELS,
            'pickers' => $this->pickers($wh->id),
            'waveMax' => (int) Setting::get('putaway', 'max_lineas_por_ola', 12, $wh->id),
            'stats' => [
                'open' => $orders->whereNotIn('status', ['DESPACHADO', 'ANULADO'])->count(),
                'urgent' => $orders->where('priority', 'URGENTE')->whereNotIn('status', ['DESPACHADO'])->count(),
                'unassigned' => $orders->where('status', 'RECIBIDO')->whereNull('assigned_to')->count(),
                'today' => SalesOrder::forWarehouse($wh->id)->where('status', 'DESPACHADO')->whereDate('dispatched_at', today())->count(),
            ],
        ]);
    }

    public function orders(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('estado');

        $orders = SalesOrder::with(['customer', 'assignee'])->withCount('lines')
            ->forWarehouse($wh->id)
            ->when($status, fn ($x) => $x->where('status', $status))
            ->when($q !== '', fn ($x) => $x->where(fn ($y) => $y->where('number', 'like', "%$q%")->orWhere('external_ref', 'like', "%$q%")->orWhere('wave_code', 'like', "%$q%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$q%")->orWhere('code', 'like', "%$q%"))))
            ->latest('ordered_at')->latest('id')->paginate(30)->withQueryString();

        return view('outbound.orders', [
            'title' => 'Pedidos', 'orders' => $orders, 'q' => $q, 'status' => $status,
            'labels' => SalesOrder::STATUS_LABELS,
        ]);
    }

    public function show(Request $request, SalesOrder $order): View
    {
        $this->assertWarehouse($request, $order);
        $order->load(['customer', 'assignee', 'lines.item.baseUom', 'lines.lot', 'lines.fromLocation', 'dispatches.vehicle', 'dispatches.driver']);

        $audit = AuditLog::where('model', SalesOrder::class)->where('model_id', $order->id)->with('user')->latest()->limit(20)->get();

        return view('outbound.show', [
            'title' => 'Pedido '.$order->number,
            'order' => $order,
            'labels' => SalesOrder::STATUS_LABELS,
            'pickers' => $this->pickers($order->warehouse_id),
            'audit' => $audit,
            'next' => $this->nextStatus($order->status),
            'totals' => $order->totals(),
        ]);
    }

    public function assign(Request $request, SalesOrder $order): RedirectResponse
    {
        $this->assertWarehouse($request, $order);
        $data = $request->validate(['assigned_to' => ['nullable', 'exists:users,id'], 'priority' => ['nullable', 'in:NORMAL,URGENTE']]);

        $order->fill(array_filter(['assigned_to' => $data['assigned_to'] ?? null, 'priority' => $data['priority'] ?? null], fn ($v) => $v !== null));
        if ($request->has('assigned_to') && ! $data['assigned_to']) {
            $order->assigned_to = null;
        }
        $order->save();
        $this->audit($request, $order, 'ASIGNACION', ['assigned_to' => $order->assigned_to, 'priority' => $order->priority]);

        return back()->with('toast', $order->assigned_to ? "Pedido {$order->number} asignado a ".($order->assignee?->name ?? '').'.' : "Pedido {$order->number} actualizado.");
    }

    /** Avanza al siguiente estado (o anula). */
    public function advance(Request $request, SalesOrder $order): RedirectResponse
    {
        $this->assertWarehouse($request, $order);
        $target = $request->input('estado');
        $role = $request->attributes->get('role');

        if ($target === 'ANULADO') {
            abort_unless(in_array($role, ['ADMIN', 'SUPERVISOR'], true), 403);
            abort_if($order->status === 'DESPACHADO', 409, 'Un pedido despachado no se anula; genera una devolución.');
            $request->validate(['reason' => ['required', 'string', 'max:200']]);
            DB::transaction(function () use ($order) {
                $this->releaseReservations($order);
                $order->update(['status' => 'ANULADO']);
            });
            $this->audit($request, $order, 'ANULACION', ['reason' => $request->input('reason')]);

            return redirect()->route('outbound.board')->with('toast', "Pedido {$order->number} anulado.");
        }

        $next = $this->nextStatus($order->status);
        abort_unless($next && $target === $next, 409, 'Transición de estado no permitida.');

        DB::transaction(function () use ($order, $next, $request) {
            switch ($next) {
                case 'PREPARACION':
                    $this->reserve($order);
                    break;
                case 'VALIDADO':
                    // Se valida lo pickeado: si el colector no reportó, se asume lo reservado.
                    foreach ($order->lines as $line) {
                        if ((float) $line->qty_picked <= 0) {
                            $line->update(['qty_picked' => $line->qty_allocated]);
                        }
                    }
                    break;
                case 'EMBALADO':
                    $packages = (int) $request->input('packages', $order->packages);
                    $order->packages = max(1, $packages);
                    break;
                case 'DESPACHADO':
                    $this->postDispatchMovements($order, $request->user());
                    $order->dispatched_at = now();
                    break;
            }
            $order->status = $next;
            $order->save();
        });

        $this->audit($request, $order, 'ESTADO_'.$next);
        NavCounts::forget($order->warehouse_id);

        return back()->with('toast', "Pedido {$order->number} → ".SalesOrder::STATUS_LABELS[$next].'.');
    }

    /** Libera una ola: toma N pedidos RECIBIDO (urgentes primero), reserva stock y los pasa a PREPARACION. */
    public function releaseWave(Request $request): RedirectResponse
    {
        $wh = $request->attributes->get('warehouse');
        $data = $request->validate(['orders' => ['nullable', 'array'], 'orders.*' => ['integer'], 'assigned_to' => ['nullable', 'exists:users,id'], 'max' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $max = (int) ($data['max'] ?? Setting::get('putaway', 'max_lineas_por_ola', 12, $wh->id));
        $query = SalesOrder::with('lines')->forWarehouse($wh->id)->where('status', 'RECIBIDO')
            ->when(! empty($data['orders']), fn ($q) => $q->whereIn('id', $data['orders']))
            ->orderByRaw("case when priority = 'URGENTE' then 0 else 1 end")->orderBy('ordered_at')->limit($max);

        $wave = 'OLA-'.now()->format('ymd-Hi');
        $count = 0;
        $short = [];

        DB::transaction(function () use ($query, $wave, $data, &$count, &$short) {
            foreach ($query->get() as $order) {
                $missing = $this->reserve($order);
                $order->fill(['status' => 'PREPARACION', 'wave_code' => $wave, 'assigned_to' => $data['assigned_to'] ?? $order->assigned_to])->save();
                $count++;
                if ($missing) {
                    $short[] = $order->number;
                }
            }
        });

        NavCounts::forget($wh->id);
        $msg = $count ? "Ola {$wave} liberada con {$count} pedidos." : 'No hay pedidos recibidos para liberar.';
        if ($short) {
            $msg .= ' Stock parcial en: '.implode(', ', $short).'.';
        }

        return redirect()->route('outbound.board')->with('toast', $msg)->with('toast_kind', $short ? 'warn' : 'ok');
    }

    public function dispatches(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $status = $request->query('estado');

        $dispatches = Dispatch::with(['vehicle', 'driver', 'orders.customer'])
            ->where('warehouse_id', $wh->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('scheduled_at')->latest('id')->paginate(20)->withQueryString();

        $ready = SalesOrder::with('customer')->forWarehouse($wh->id)->whereIn('status', ['EMBALADO', 'VALIDADO'])
            ->whereDoesntHave('dispatches', fn ($q) => $q->whereIn('status', ['CARGANDO', 'EN_RUTA']))
            ->orderBy('ordered_at')->get();

        return view('outbound.dispatches', [
            'title' => 'Despachos',
            'dispatches' => $dispatches, 'status' => $status,
            'ready' => $ready,
            'vehicles' => Vehicle::with('driver')->where('status', '!=', 'INACTIVO')->orderBy('plate')->get(),
            'drivers' => Driver::where('status', 'ACTIVO')->orderBy('last_name')->get(),
            'nextNumber' => $this->nextDispatchNumber($wh->id),
            'labels' => Dispatch::STATUS_LABELS,
        ]);
    }

    public function storeDispatch(Request $request): RedirectResponse
    {
        $wh = $request->attributes->get('warehouse');
        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'destination' => ['nullable', 'string', 'max:120'],
            'scheduled_at' => ['nullable', 'date'],
            'orders' => ['required', 'array', 'min:1'],
            'orders.*' => ['integer'],
        ], [], ['vehicle_id' => 'camión', 'driver_id' => 'chofer', 'orders' => 'pedidos']);

        $orders = SalesOrder::forWarehouse($wh->id)->whereIn('id', $data['orders'])->whereIn('status', ['EMBALADO', 'VALIDADO'])->get();
        abort_if($orders->isEmpty(), 422, 'Ningún pedido válido para despachar.');

        $dispatch = DB::transaction(function () use ($wh, $data, $orders, $request) {
            $d = Dispatch::create([
                'warehouse_id' => $wh->id,
                'number' => $this->nextDispatchNumber($wh->id),
                'vehicle_id' => $data['vehicle_id'], 'driver_id' => $data['driver_id'],
                'destination' => $data['destination'] ?? $orders->pluck('customer.city')->filter()->unique()->implode(' / '),
                'status' => 'CARGANDO',
                'packages' => (int) $orders->sum('packages'),
                'scheduled_at' => $data['scheduled_at'] ?? now(),
                'created_by' => $request->user()->id,
            ]);
            foreach ($orders as $o) {
                $d->orders()->attach($o->id, ['packages_verified' => 0]);
                if ($o->status === 'VALIDADO') {
                    $o->update(['status' => 'EMBALADO', 'packages' => max(1, $o->packages)]);
                }
            }

            return $d;
        });

        return redirect()->route('outbound.dispatches')->with('toast', "Despacho {$dispatch->number} creado con {$orders->count()} pedidos.");
    }

    /** CARGANDO → EN_RUTA (descuenta stock de todos los pedidos) → ENTREGADO; o ANULADO. */
    public function dispatchStatus(Request $request, Dispatch $dispatch): RedirectResponse
    {
        abort_unless($dispatch->warehouse_id === $request->attributes->get('warehouse')->id, 404);
        $target = $request->input('estado');
        $allowed = ['CARGANDO' => ['EN_RUTA', 'ANULADO'], 'EN_RUTA' => ['ENTREGADO'], 'ENTREGADO' => [], 'ANULADO' => []];
        abort_unless(in_array($target, $allowed[$dispatch->status] ?? [], true), 409, 'Transición no permitida.');

        DB::transaction(function () use ($dispatch, $target, $request) {
            if ($target === 'EN_RUTA') {
                foreach ($dispatch->orders()->with('lines')->get() as $order) {
                    if ($order->status !== 'DESPACHADO') {
                        $this->postDispatchMovements($order, $request->user());
                        $order->update(['status' => 'DESPACHADO', 'dispatched_at' => now()]);
                        $this->audit($request, $order, 'ESTADO_DESPACHADO', ['dispatch' => $dispatch->number]);
                    }
                }
                $dispatch->departed_at = now();
            }
            if ($target === 'ENTREGADO') {
                $dispatch->delivered_at = now();
            }
            if ($target === 'ANULADO') {
                $dispatch->orders()->detach();
            }
            $dispatch->status = $target;
            $dispatch->save();
        });

        NavCounts::forget($dispatch->warehouse_id);

        return back()->with('toast', "Despacho {$dispatch->number} → ".Dispatch::STATUS_LABELS[$target].'.');
    }

    // -----------------------------------------------------------------
    // Reserva y descuento
    // -----------------------------------------------------------------

    /**
     * Reserva stock para cada línea (FEFO/FIFO según parámetro). Marca
     * qty_allocated y from_location_id. Devuelve true si alguna línea quedó corta.
     */
    private function reserve(SalesOrder $order): bool
    {
        $rule = Setting::get('putaway', 'rotacion', 'FEFO', $order->warehouse_id);
        $short = false;

        foreach ($order->lines()->with('lot')->get() as $line) {
            $need = (float) $line->qty_ordered - (float) $line->qty_allocated;
            if ($need <= 0) {
                continue;
            }

            $candidates = StockBalance::with('lot')->forWarehouse($order->warehouse_id)
                ->where('item_id', $line->item_id)->where('status', 'BUENO')
                ->when($line->lot_id, fn ($q) => $q->where('lot_id', $line->lot_id))
                ->whereRaw('qty - qty_allocated > 0')
                ->lockForUpdate()->get()
                ->sortBy(fn ($b) => match ($rule) {
                    'FIFO' => ($b->lot?->manufactured_at?->timestamp ?? $b->created_at?->timestamp ?? 0),
                    'LIFO' => -($b->lot?->manufactured_at?->timestamp ?? $b->created_at?->timestamp ?? 0),
                    default => ($b->lot?->expires_at?->timestamp ?? PHP_INT_MAX),
                })->values();

            foreach ($candidates as $b) {
                if ($need <= 0) {
                    break;
                }
                $free = (float) $b->qty - (float) $b->qty_allocated;
                $take = min($free, $need);
                StockBalance::whereKey($b->id)->update(['qty_allocated' => DB::raw('qty_allocated + '.number_format($take, 4, '.', ''))]);
                // Una línea reserva de una sola ubicación/lote; si necesita más se parte en otra línea.
                if ((float) $line->qty_allocated <= 0) {
                    $line->fill(['qty_allocated' => $take, 'from_location_id' => $b->location_id, 'lot_id' => $b->lot_id])->save();
                } else {
                    $order->lines()->create([
                        'line_no' => ($order->lines()->max('line_no') ?? 0) + 1, 'item_id' => $line->item_id, 'lot_id' => $b->lot_id,
                        'from_location_id' => $b->location_id, 'qty_ordered' => $take, 'qty_allocated' => $take, 'uom_id' => $line->uom_id,
                    ]);
                    $line->qty_ordered = (float) $line->qty_ordered - $take;
                    $line->save();
                }
                $need -= $take;
            }
            if ($need > 0.00005) {
                $short = true;
            }
        }

        return $short;
    }

    private function releaseReservations(SalesOrder $order): void
    {
        foreach ($order->lines as $line) {
            if ((float) $line->qty_allocated > 0 && $line->from_location_id) {
                StockBalance::forWarehouse($order->warehouse_id)->where('location_id', $line->from_location_id)->where('item_id', $line->item_id)
                    ->where('lot_id', $line->lot_id)->where('status', 'BUENO')
                    ->update(['qty_allocated' => DB::raw('greatest(0, qty_allocated - '.number_format((float) $line->qty_allocated, 4, '.', '').')')]);
                $line->update(['qty_allocated' => 0]);
            }
        }
    }

    /** Un movimiento DESPACHO por línea, desde la ubicación reservada. Idempotente por línea. */
    private function postDispatchMovements(SalesOrder $order, User $user): void
    {
        foreach ($order->lines()->with('item')->get() as $line) {
            $qty = (float) ($line->qty_picked > 0 ? $line->qty_picked : $line->qty_allocated);
            if ($qty <= 0 || ! $line->from_location_id) {
                continue;
            }
            // Liberar la reserva antes de descontar (la columna qty_available es generada).
            StockBalance::forWarehouse($order->warehouse_id)->where('location_id', $line->from_location_id)->where('item_id', $line->item_id)
                ->where('lot_id', $line->lot_id)->where('status', 'BUENO')
                ->update(['qty_allocated' => DB::raw('greatest(0, qty_allocated - '.number_format((float) $line->qty_allocated, 4, '.', '').')')]);

            $this->ledger->post(new MovementData(
                clientUuid: (string) Str::uuid5(Str::uuid5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'wms-dispatch'), 'so-line-'.$line->id),
                type: 'DESPACHO',
                warehouseId: $order->warehouse_id,
                itemId: $line->item_id,
                lotId: (int) $line->lot_id,
                qty: $qty,
                uomId: $line->uom_id,
                userId: $user->id,
                fromLocationId: $line->from_location_id,
                toLocationId: null,
                documentType: 'PEDIDO',
                documentId: $order->id,
                documentLineId: $line->id,
                deviceId: 'WEB',
            ));
            $line->update(['qty_picked' => $qty, 'qty_allocated' => 0]);
        }
    }

    // -----------------------------------------------------------------

    private function nextStatus(string $current): ?string
    {
        $i = array_search($current, SalesOrder::STATUSES, true);

        return $i === false ? null : (SalesOrder::STATUSES[$i + 1] ?? null);
    }

    private function pickers(int $whId)
    {
        return User::where('is_active', true)->whereHas('warehouses', fn ($q) => $q->where('warehouses.id', $whId))->orderBy('name')->get(['id', 'name', 'username']);
    }

    private function nextDispatchNumber(int $whId): string
    {
        $prefix = Setting::get('codigos', 'prefijo_despacho', 'DSP', $whId);
        $last = Dispatch::where('warehouse_id', $whId)->where('number', 'like', $prefix.'-'.now()->format('ym').'%')->max('number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('%s-%s%04d', $prefix, now()->format('ym'), $seq);
    }

    private function assertWarehouse(Request $request, SalesOrder $order): void
    {
        abort_unless($order->warehouse_id === $request->attributes->get('warehouse')->id, 404);
    }

    private function audit(Request $request, SalesOrder $order, string $action, array $after = []): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'model' => SalesOrder::class, 'model_id' => $order->id, 'action' => $action, 'after' => $after ?: null, 'ip' => $request->ip()]);
    }
}
