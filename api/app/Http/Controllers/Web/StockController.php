<?php

namespace App\Http\Controllers\Web;

use App\Data\MovementData;
use App\Http\Controllers\Controller;
use App\Models\CycleCount;
use App\Models\CycleCountLine;
use App\Models\Item;
use App\Models\ItemWarehouse;
use App\Models\Location;
use App\Models\Lot;
use App\Models\ReasonCode;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\LotResolver;
use App\Services\StockLedger;
use App\Support\NavCounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(private readonly StockLedger $ledger, private readonly LotResolver $lots) {}

    /* ---------------- Saldos ---------------- */
    public function balances(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('estado');

        $rows = StockBalance::forWarehouse($wh->id)->where('qty', '>', 0)
            ->with(['location.zone', 'item.baseUom', 'lot'])
            ->when($status === 'RESERVADO', fn ($x) => $x->where('qty_allocated', '>', 0))
            ->when($status && $status !== 'RESERVADO' && $status !== 'VENCIDO', fn ($x) => $x->where('status', $status))
            ->when($status === 'VENCIDO', fn ($x) => $x->whereHas('lot', fn ($l) => $l->whereNotNull('expires_at')->where('expires_at', '<', today())))
            ->when($q !== '', fn ($x) => $x->where(fn ($y) => $y
                ->whereHas('item', fn ($i) => $i->where('sku', 'like', "%$q%")->orWhere('name', 'like', "%$q%"))
                ->orWhereHas('location', fn ($l) => $l->where('code', 'like', "%$q%"))
                ->orWhereHas('lot', fn ($l) => $l->where('code', 'like', "%$q%"))))
            ->join('locations', 'locations.id', '=', 'stock_balances.location_id')
            ->orderBy('locations.sort_seq')->orderBy('locations.code')
            ->select('stock_balances.*')
            ->paginate(40)->withQueryString();

        $totals = StockBalance::forWarehouse($wh->id)->where('qty', '>', 0)
            ->selectRaw('COUNT(*) as rows_count, COUNT(DISTINCT item_id) as skus, SUM(qty) as units, SUM(qty_allocated) as allocated')->first();

        return view('stock.balances', ['title' => 'Stock por ubicación', 'rows' => $rows, 'totals' => $totals, 'q' => $q, 'status' => $status, 'tab' => 'balances']);
    }

    /* ---------------- Kardex ---------------- */
    public function kardex(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $q = trim((string) $request->query('q', ''));
        $from = $request->query('desde', now()->subDays(7)->toDateString());
        $to = $request->query('hasta', today()->toDateString());
        $type = $request->query('tipo');

        $rows = StockMovement::forWarehouse($wh->id)
            ->with(['item', 'lot', 'fromLocation', 'toLocation', 'user', 'reasonCode'])
            ->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($type, fn ($x) => $x->where('type', $type))
            ->when($q !== '', fn ($x) => $x->where(fn ($y) => $y
                ->whereHas('item', fn ($i) => $i->where('sku', 'like', "%$q%")->orWhere('name', 'like', "%$q%"))
                ->orWhereHas('lot', fn ($l) => $l->where('code', 'like', "%$q%"))
                ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%$q%")->orWhere('name', 'like', "%$q%"))
                ->orWhere('device_id', 'like', "%$q%")))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('stock.kardex', ['title' => 'Kardex', 'rows' => $rows, 'q' => $q, 'from' => $from, 'to' => $to, 'type' => $type, 'tab' => 'kardex']);
    }

    /* ---------------- Ajustes ---------------- */
    public function adjustments(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $loc = trim((string) $request->query('ubicacion', ''));

        $location = $loc !== '' ? Location::forWarehouse($wh->id)->where('code', $loc)->orWhere(fn ($x) => $x->where('warehouse_id', $wh->id)->where('barcode', $loc))->first() : null;
        $balances = $location ? StockBalance::where('location_id', $location->id)->where('qty', '>', 0)->with(['item.baseUom', 'lot'])->get() : collect();

        return view('stock.adjustments', [
            'title' => 'Ajustes de stock', 'tab' => 'adjustments',
            'loc' => $loc, 'location' => $location, 'balances' => $balances,
            'pending' => StockAdjustment::where('warehouse_id', $wh->id)->where('status', 'PENDIENTE')->with(['location', 'item', 'lot', 'requester'])->latest()->get(),
            'history' => StockAdjustment::where('warehouse_id', $wh->id)->where('status', '!=', 'PENDIENTE')->with(['location', 'item', 'approver'])->latest('resolved_at')->take(20)->get(),
            'reasons' => ReasonCode::orderBy('name')->get(),
            'items' => Item::active()->orderBy('sku')->get(['id', 'sku', 'name', 'tracks_lot']),
            'locations' => Location::forWarehouse($wh->id)->active()->orderBy('sort_seq')->get(['id', 'code']),
        ]);
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        $wh = $request->attributes->get('warehouse');
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'lot_code' => ['nullable', 'string', 'max:40'],
            'type' => ['required', 'in:AJUSTE_POS,AJUSTE_NEG,MERMA'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'reason_code_id' => ['required', 'integer', 'exists:reason_codes,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['reason_code_id' => 'motivo', 'qty' => 'cantidad', 'location_id' => 'ubicación', 'item_id' => 'producto']);

        $location = Location::findOrFail($data['location_id']);
        abort_unless($location->warehouse_id === $wh->id, 422, 'La ubicación no pertenece al almacén de trabajo.');

        $lotId = $data['lot_id'] ?? null;
        if (! $lotId) {
            $lotId = $this->lots->resolve(Item::findOrFail($data['item_id']), $data['lot_code'] ?? null)->id;
        }

        $adj = StockAdjustment::create([
            'warehouse_id' => $wh->id, 'location_id' => $location->id, 'item_id' => $data['item_id'], 'lot_id' => $lotId,
            'type' => $data['type'], 'qty' => $data['qty'], 'reason_code_id' => $data['reason_code_id'], 'note' => $data['note'] ?? null,
            'status' => 'PENDIENTE', 'requested_by' => $request->user()->id,
        ]);

        // Un encargado/administrador aprueba en el acto si el motivo no exige aprobación;
        // si la exige, queda para un segundo par de ojos.
        $reason = ReasonCode::find($data['reason_code_id']);
        $role = $request->attributes->get('role');
        $tolerance = (float) \App\Models\Setting::get('reglas', 'tolerancia_ajuste_sin_aprobacion', 0, $wh->id);

        if (in_array($role, ['ADMIN', 'SUPERVISOR'], true) && (! $reason?->requires_approval || (float) $data['qty'] <= $tolerance)) {
            $this->applyAdjustment($adj, $request->user());

            return back()->with('toast', 'Ajuste aplicado al saldo y registrado en el kardex.');
        }

        return back()->with('toast', 'Ajuste enviado a aprobación de un encargado.')->with('toast_kind', 'info');
    }

    public function resolveAdjustment(Request $request, StockAdjustment $adjustment): RedirectResponse
    {
        abort_unless($adjustment->warehouse_id === $request->attributes->get('warehouse')?->id, 404);
        abort_unless($adjustment->status === 'PENDIENTE', 422, 'El ajuste ya fue resuelto.');
        $action = $request->validate(['accion' => ['required', 'in:aprobar,rechazar']])['accion'];

        if ($action === 'rechazar') {
            $adjustment->forceFill(['status' => 'RECHAZADO', 'approved_by' => $request->user()->id, 'resolved_at' => now()])->save();

            return back()->with('toast', 'Ajuste rechazado.')->with('toast_kind', 'bad');
        }

        $this->applyAdjustment($adjustment, $request->user());

        return back()->with('toast', 'Ajuste aprobado y aplicado al saldo.');
    }

    private function applyAdjustment(StockAdjustment $adj, User $approver): void
    {
        DB::transaction(function () use ($adj, $approver) {
            $item = $adj->item;
            $positive = $adj->type === 'AJUSTE_POS';

            $movement = $this->ledger->post(new MovementData(
                clientUuid: (string) Str::uuid(),
                type: $positive ? 'AJUSTE_POS' : 'AJUSTE_NEG',
                warehouseId: $adj->warehouse_id,
                itemId: $adj->item_id,
                lotId: $adj->lot_id,
                qty: (float) $adj->qty,
                uomId: $item->base_uom_id,
                userId: $approver->id,
                fromLocationId: $positive ? null : $adj->location_id,
                toLocationId: $positive ? $adj->location_id : null,
                documentType: 'AJUSTE',
                documentId: $adj->id,
                reasonCodeId: $adj->reason_code_id,
                deviceId: 'WEB',
            ));

            $adj->forceFill(['status' => 'APROBADO', 'approved_by' => $approver->id, 'movement_id' => $movement->id, 'resolved_at' => now()])->save();
        });
    }

    /* ---------------- Inventario cíclico ---------------- */
    public function counts(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $counts = CycleCount::where('warehouse_id', $wh->id)->with(['lines', 'responsible'])->latest('id')->paginate(20);

        $last = CycleCount::where('warehouse_id', $wh->id)->where('status', 'FINALIZADO')->with('lines')->latest('closed_at')->take(8)->get()->reverse();
        $iraSeries = $last->map(fn ($c) => $c->progress()['ira'] ?? 0)->values()->all();

        // Cobertura del plan: % de ubicaciones con stock contadas en la ventana de cada clase.
        $plan = [];
        foreach ([['A', 30], ['B', 180], ['C', 365]] as [$cls, $days]) {
            $locIds = StockBalance::forWarehouse($wh->id)->where('qty', '>', 0)
                ->whereIn('item_id', ItemWarehouse::where('warehouse_id', $wh->id)->where('abc_class', $cls)->pluck('item_id'))
                ->distinct()->pluck('location_id');
            $counted = $locIds->isEmpty() ? 0 : CycleCountLine::whereIn('location_id', $locIds)->where('counted_at', '>=', now()->subDays($days))->distinct()->count('location_id');
            $plan[] = ['cls' => $cls, 'days' => $days, 'pct' => $locIds->isEmpty() ? null : round($counted / $locIds->count() * 100)];
        }

        return view('stock.counts', [
            'title' => 'Inventario cíclico', 'tab' => 'counts', 'counts' => $counts, 'iraSeries' => $iraSeries, 'plan' => $plan,
            'operators' => $wh->users()->orderBy('name')->get(),
            'zones' => $wh->zones()->whereIn('type', ['ALMACENAJE', 'PICKING'])->orderBy('code')->get(),
        ]);
    }

    public function storeCount(Request $request): RedirectResponse
    {
        $wh = $request->attributes->get('warehouse');
        $data = $request->validate([
            'type' => ['required', 'in:GENERAL,UBICACION,PRODUCTO,CICLICO_A,CICLICO_B,CICLICO_C,EXCEPCION'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'blind' => ['nullable', 'boolean'],
            'blocks_picking' => ['nullable', 'boolean'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $count = DB::transaction(function () use ($data, $wh, $request) {
            $number = ((int) CycleCount::where('warehouse_id', $wh->id)->max('number')) + 1;
            $count = CycleCount::create([
                'warehouse_id' => $wh->id, 'number' => $number, 'type' => $data['type'],
                'blind' => (bool) ($data['blind'] ?? true), 'blocks_picking' => (bool) ($data['blocks_picking'] ?? false),
                'status' => 'HABILITADO', 'responsible_id' => $data['responsible_id'] ?? $request->user()->id, 'opened_at' => now(),
            ]);

            // Alcance: ubicaciones a contar según el tipo.
            $locations = Location::forWarehouse($wh->id)->active();
            if (! empty($data['zone_id'])) {
                $locations->where('zone_id', $data['zone_id']);
            }
            if (str_starts_with($data['type'], 'CICLICO_')) {
                $cls = substr($data['type'], -1);
                $itemIds = ItemWarehouse::where('warehouse_id', $wh->id)->where('abc_class', $cls)->pluck('item_id');
                $locations->whereHas('balances', fn ($b) => $b->where('qty', '>', 0)->whereIn('item_id', $itemIds));
            } elseif ($data['type'] === 'EXCEPCION') {
                // Ubicaciones que quedaron en cero en los últimos 7 días.
                $locations->whereHas('balances', fn ($b) => $b->where('qty', '<=', 0)->where('last_movement_at', '>=', now()->subDays(7)));
            } elseif ($data['type'] !== 'GENERAL') {
                $locations->whereHas('zone', fn ($z) => $z->whereIn('type', ['ALMACENAJE', 'PICKING']));
            }

            foreach ($locations->get() as $loc) {
                $balances = StockBalance::where('location_id', $loc->id)->where('qty', '>', 0)->get();
                if ($balances->isEmpty()) {
                    CycleCountLine::create(['cycle_count_id' => $count->id, 'location_id' => $loc->id, 'qty_system' => 0]);
                    continue;
                }
                foreach ($balances as $b) {
                    CycleCountLine::create(['cycle_count_id' => $count->id, 'location_id' => $loc->id, 'item_id' => $b->item_id, 'lot_id' => $b->lot_id, 'qty_system' => $b->qty]);
                }
            }

            return $count;
        });

        return redirect()->route('stock.counts.show', $count)->with('toast', "Conteo {$count->number} habilitado con {$count->lines()->count()} líneas. Ya aparece en los colectores.");
    }

    public function showCount(Request $request, CycleCount $count): View
    {
        abort_unless($count->warehouse_id === $request->attributes->get('warehouse')?->id, 404);
        $count->load(['lines.location', 'lines.item', 'lines.lot', 'lines.counter', 'responsible']);

        return view('stock.count-show', ['title' => 'Conteo '.$count->number, 'count' => $count, 'tab' => 'counts', 'progress' => $count->progress()]);
    }

    public function closeCount(Request $request, CycleCount $count): RedirectResponse
    {
        abort_unless($count->warehouse_id === $request->attributes->get('warehouse')?->id, 404);
        abort_unless($count->status !== 'FINALIZADO', 422, 'El conteo ya está finalizado.');

        $reasonPos = ReasonCode::where('code', 'CONTEO_POS')->first();
        $reasonNeg = ReasonCode::where('code', 'CONTEO_NEG')->first();
        $applied = 0;

        DB::transaction(function () use ($count, $request, $reasonPos, $reasonNeg, &$applied) {
            foreach ($count->lines()->where('status', 'DIFERENCIA')->with('item')->get() as $line) {
                $diff = (float) $line->qty_counted - (float) $line->qty_system;
                if (abs($diff) < 0.00005 || ! $line->item_id || ! $line->lot_id) {
                    $line->forceFill(['status' => 'APROBADA'])->save();
                    continue;
                }
                $this->ledger->post(new MovementData(
                    clientUuid: (string) Str::uuid(),
                    type: $diff > 0 ? 'AJUSTE_POS' : 'AJUSTE_NEG',
                    warehouseId: $count->warehouse_id, itemId: $line->item_id, lotId: $line->lot_id,
                    qty: abs($diff), uomId: $line->item->base_uom_id, userId: $request->user()->id,
                    fromLocationId: $diff > 0 ? null : $line->location_id, toLocationId: $diff > 0 ? $line->location_id : null,
                    documentType: 'CONTEO', documentId: $count->id, documentLineId: $line->id,
                    reasonCodeId: $diff > 0 ? $reasonPos?->id : $reasonNeg?->id, deviceId: 'WEB',
                ));
                $line->forceFill(['status' => 'APROBADA'])->save();
                $applied++;
            }
            $count->forceFill(['status' => 'FINALIZADO', 'closed_at' => now()])->save();
        });

        return back()->with('toast', "Conteo {$count->number} finalizado. $applied ajustes aplicados con tu aprobación.");
    }

    /* ---------------- ABC y máx/mín ---------------- */
    public function abc(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $since = now()->subDays(90);
        $outbound = StockMovement::forWarehouse($wh->id)->whereIn('type', ['PICKING', 'DESPACHO'])->where('occurred_at', '>=', $since)
            ->selectRaw('item_id, SUM(qty) as q')->groupBy('item_id')->pluck('q', 'item_id');
        $stock = StockBalance::forWarehouse($wh->id)->selectRaw('item_id, SUM(qty) as q')->groupBy('item_id')->pluck('q', 'item_id');
        $settings = ItemWarehouse::where('warehouse_id', $wh->id)->get()->keyBy('item_id');

        $items = Item::active()->orderBy('sku')->get()->map(function ($i) use ($outbound, $stock, $settings, $wh) {
            $bal = StockBalance::forWarehouse($wh->id)->where('item_id', $i->id)->where('qty', '>', 0)->with('location.zone')->first();
            $cls = $settings[$i->id]->abc_class ?? null;
            $zoneType = $bal?->location?->zone?->type;
            $misplaced = $cls === 'A' ? ! in_array($zoneType, ['PICKING', null], true) : ($cls === 'C' ? $zoneType === 'PICKING' : false);

            return ['item' => $i, 'cls' => $cls, 'out' => (float) ($outbound[$i->id] ?? 0), 'stock' => (float) ($stock[$i->id] ?? 0), 'zone' => $bal?->location?->zone?->name, 'misplaced' => $misplaced];
        })->sortByDesc('out')->values();

        $groups = collect(['A', 'B', 'C'])->map(fn ($c) => ['cls' => $c, 'n' => $items->where('cls', $c)->count(), 'u' => $items->where('cls', $c)->sum('stock')]);

        return view('stock.abc', ['title' => 'Rotación ABC', 'tab' => 'abc', 'items' => $items, 'groups' => $groups, 'totalUnits' => max($items->sum('stock'), 1)]);
    }

    public function minmax(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $filter = $request->query('f');
        $stock = StockBalance::forWarehouse($wh->id)->selectRaw('item_id, SUM(qty) as q')->groupBy('item_id')->pluck('q', 'item_id');
        $settings = ItemWarehouse::where('warehouse_id', $wh->id)->get()->keyBy('item_id');
        $out30 = StockMovement::forWarehouse($wh->id)->whereIn('type', ['PICKING', 'DESPACHO'])->where('occurred_at', '>=', now()->subDays(30))
            ->selectRaw('item_id, SUM(qty) as q')->groupBy('item_id')->pluck('q', 'item_id');

        $rows = Item::active()->orderBy('sku')->get()->map(function ($i) use ($stock, $settings, $out30) {
            $s = $settings[$i->id] ?? null;
            $min = (float) ($s?->min_stock ?? $i->min_stock ?? 0); $max = (float) ($s?->max_stock ?? $i->max_stock ?? 0);
            $q = (float) ($stock[$i->id] ?? 0); $daily = (float) ($out30[$i->id] ?? 0) / 30;
            $sit = $min > 0 && $q < $min ? 'BAJO' : ($max > 0 && $q > $max ? 'SOBRE' : 'OK');

            return ['item' => $i, 'cls' => $s?->abc_class, 'min' => $min, 'max' => $max, 'qty' => $q, 'days' => $daily > 0 ? (int) floor($q / $daily) : null, 'sit' => $sit];
        })->when($filter, fn ($c) => $c->where('sit', $filter))->sortBy(fn ($r) => $r['sit'] === 'BAJO' ? 0 : ($r['sit'] === 'SOBRE' ? 1 : 2))->values();

        return view('stock.minmax', ['title' => 'Máximos y mínimos', 'tab' => 'minmax', 'rows' => $rows, 'filter' => $filter]);
    }
}
