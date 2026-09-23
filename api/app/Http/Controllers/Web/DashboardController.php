<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CycleCount;
use App\Models\Device;
use App\Models\Item;
use App\Models\Location;
use App\Models\Lot;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Warehouse $wh */
        $wh = $request->attributes->get('warehouse');
        abort_unless($wh, 403, 'No tienes almacenes asignados. Pide a un administrador que te asigne uno.');

        // Ocupación: ubicaciones de almacenaje/picking con saldo.
        $storage = Location::forWarehouse($wh->id)->active()
            ->whereHas('zone', fn ($q) => $q->whereIn('type', ['ALMACENAJE', 'PICKING']));
        $usable = (clone $storage)->count();
        $occupied = (clone $storage)->whereHas('balances', fn ($q) => $q->where('qty', '>', 0))->count();

        // Pedidos.
        $orders = SalesOrder::forWarehouse($wh->id)->open();
        $ordersOpen = (clone $orders)->count();
        $ordersUrgent = (clone $orders)->where('priority', 'URGENTE')->count();
        $ordersUnassigned = (clone $orders)->whereNull('assigned_to')->count();
        $ordersTotal = SalesOrder::forWarehouse($wh->id)->count();

        // IRA del último conteo finalizado.
        $lastCount = CycleCount::where('warehouse_id', $wh->id)->where('status', 'FINALIZADO')->with('lines')->latest('closed_at')->first();
        $ira = $lastCount?->progress()['ira'];

        // Flujo de la semana (unidades por día, entradas vs. salidas).
        $from = now()->startOfDay()->subDays(6);
        $rows = StockMovement::forWarehouse($wh->id)
            ->where('occurred_at', '>=', $from)
            ->selectRaw('DATE(occurred_at) as d, type, SUM(qty) as q')
            ->groupBy('d', 'type')->get();
        $labels = []; $in = []; $out = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $from->copy()->addDays($i);
            $labels[] = ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'][$day->dayOfWeek];
            $key = $day->toDateString();
            $in[] = (float) $rows->where('d', $key)->whereIn('type', StockMovement::INBOUND_TYPES)->sum('q');
            $out[] = (float) $rows->where('d', $key)->whereIn('type', StockMovement::OUTBOUND_TYPES)->sum('q');
        }

        // Ocupación por rack (zona).
        $racks = $wh->zones()->whereIn('type', ['ALMACENAJE', 'PICKING'])->orderBy('code')->get()->map(function ($z) {
            $total = $z->locations()->count();
            $occ = $z->locations()->whereHas('balances', fn ($q) => $q->where('qty', '>', 0))->count();

            return ['l' => $z->code, 'v' => $total ? round($occ / $total * 100) : 0];
        })->values()->all();

        // Bajo mínimo.
        $stockByItem = StockBalance::forWarehouse($wh->id)->selectRaw('item_id, SUM(qty) as q')->groupBy('item_id')->pluck('q', 'item_id');
        $lowStock = Item::active()->whereNotNull('min_stock')->where('min_stock', '>', 0)->get()
            ->filter(fn ($i) => (float) ($stockByItem[$i->id] ?? 0) < (float) $i->min_stock)
            ->map(fn ($i) => ['item' => $i, 'qty' => (float) ($stockByItem[$i->id] ?? 0)])
            ->sortBy(fn ($r) => $r['qty'] / max((float) $r['item']->min_stock, 1))->take(6);

        // Alertas.
        $alerts = [];
        $offline = Device::where('warehouse_id', $wh->id)->where('is_active', true)->with('user')
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subMinutes(10)))->get();
        foreach ($offline->take(2) as $d) {
            $alerts[] = ['k' => $d->pending_queue ? 'bad' : 'warn', 't' => "{$d->serial} sin conexión".($d->pending_queue ? " con {$d->pending_queue} movimientos en cola" : ''), 's' => ($d->user?->name ?? 'sin usuario').' · '.($d->last_seen_at ? 'visto '.$d->last_seen_at->diffForHumans() : 'nunca reportó')];
        }
        foreach ($lowStock->take(2) as $r) {
            $alerts[] = ['k' => 'warn', 't' => "{$r['item']->sku} por debajo del mínimo (".\App\Support\Ui::qty($r['qty']).' < '.\App\Support\Ui::qty($r['item']->min_stock).')', 's' => $r['item']->name];
        }
        $pendingAdj = StockAdjustment::where('warehouse_id', $wh->id)->where('status', 'PENDIENTE')->count();
        if ($pendingAdj) {
            $alerts[] = ['k' => 'warn', 't' => "$pendingAdj ajustes de stock esperan aprobación", 's' => 'Stock e inventario › Ajustes'];
        }
        $expiring = Lot::whereNotNull('expires_at')->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->whereHas('item', fn ($q) => $q->whereHas('balances', fn ($b) => $b->where('warehouse_id', $wh->id)->where('qty', '>', 0)))->count();
        if ($expiring) {
            $alerts[] = ['k' => 'warn', 't' => "$expiring lotes vencen en los próximos 30 días", 's' => 'FEFO activo en picking'];
        }
        $recentReceipts = Receipt::forWarehouse($wh->id)->where('status', 'CERRADA')->latest('closed_at')->first();
        if ($recentReceipts) {
            $alerts[] = ['k' => 'ok', 't' => "Recepción {$recentReceipts->number} cerrada", 's' => 'hace '.$recentReceipts->closed_at?->diffForHumans(null, true)];
        }

        $devices = Device::where('warehouse_id', $wh->id)->where('is_active', true)->with('user')->orderByDesc('last_seen_at')->take(6)->get();

        return view('dashboard.index', [
            'title' => 'Inicio',
            'usable' => $usable, 'occupied' => $occupied,
            'ordersOpen' => $ordersOpen, 'ordersUrgent' => $ordersUrgent, 'ordersUnassigned' => $ordersUnassigned, 'ordersTotal' => $ordersTotal,
            'ira' => $ira, 'lastCount' => $lastCount,
            'flow' => ['labels' => $labels, 'in' => $in, 'out' => $out],
            'racks' => $racks, 'lowStock' => $lowStock, 'alerts' => $alerts, 'devices' => $devices,
            'receiptsOpen' => Receipt::forWarehouse($wh->id)->whereIn('status', ['ABIERTA', 'EN_PROCESO'])->count(),
            'movementsToday' => StockMovement::forWarehouse($wh->id)->whereDate('occurred_at', today())->count(),
            'queueTotal' => Device::where('warehouse_id', $wh->id)->sum('pending_queue'),
        ]);
    }

    /** Búsqueda global: SKU, ubicación, pedido, ingreso o lote. */
    public function search(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $q = trim((string) $request->query('q', ''));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        $results = [];
        if (mb_strlen($q) >= 2) {
            $results = [
                'Productos' => Item::where('sku', 'like', $like)->orWhere('name', 'like', $like)->orWhere('factory_code', 'like', $like)->take(8)->get()
                    ->map(fn ($i) => ['t' => "<span class=\"mono\">{$i->sku}</span> · ".e($i->name), 'u' => route('items.show', $i)]),
                'Ubicaciones' => Location::forWarehouse($wh->id)->where(fn ($x) => $x->where('code', 'like', $like)->orWhere('barcode', 'like', $like))->take(8)->get()
                    ->map(fn ($l) => ['t' => "<span class=\"mono\">{$l->code}</span> · ".e($l->zone?->name), 'u' => route('map.index', ['zona' => $l->zone_id, 'ubicacion' => $l->id])]),
                'Pedidos' => SalesOrder::forWarehouse($wh->id)->where(fn ($x) => $x->where('number', 'like', $like)->orWhere('external_ref', 'like', $like))->with('customer')->take(8)->get()
                    ->map(fn ($o) => ['t' => "<span class=\"mono\">{$o->number}</span> · ".e($o->customer?->name).' '.\App\Support\Ui::pill($o->status), 'u' => route('outbound.show', $o)]),
                'Ingresos' => Receipt::forWarehouse($wh->id)->where(fn ($x) => $x->where('number', 'like', $like)->orWhere('external_ref', 'like', $like))->take(8)->get()
                    ->map(fn ($r) => ['t' => "<span class=\"mono\">{$r->number}</span> · ".\App\Support\Ui::label($r->type).' '.\App\Support\Ui::pill($r->status), 'u' => route('receipts.show', $r)]),
                'Lotes' => Lot::where('code', 'like', $like)->with('item')->take(8)->get()
                    ->map(fn ($l) => ['t' => "<span class=\"mono\">{$l->code}</span> · ".e($l->item?->sku), 'u' => route('stock.balances', ['q' => $l->code])]),
            ];
        }

        return view('dashboard.search', ['title' => 'Buscar', 'q' => $q, 'results' => $results]);
    }
}
