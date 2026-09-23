<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemWarehouse;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Uom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $q = trim((string) $request->query('q', ''));
        $cls = $request->query('clase');
        $inactive = $request->boolean('inactivos');

        $settings = ItemWarehouse::where('warehouse_id', $wh->id)->get()->keyBy('item_id');
        $stock = StockBalance::forWarehouse($wh->id)->selectRaw('item_id, SUM(qty) as q, COUNT(DISTINCT location_id) as locs')->where('qty', '>', 0)->groupBy('item_id')->get()->keyBy('item_id');

        $items = Item::with('baseUom')
            ->when(! $inactive, fn ($x) => $x->where('is_active', true))
            ->when($inactive, fn ($x) => $x->where('is_active', false))
            ->when($q !== '', fn ($x) => $x->where(fn ($y) => $y->where('sku', 'like', "%$q%")->orWhere('name', 'like', "%$q%")->orWhere('subcategory', 'like', "%$q%")->orWhere('factory_code', 'like', "%$q%")))
            ->when($cls, fn ($x) => $x->whereIn('id', $settings->where('abc_class', $cls)->keys()))
            ->orderBy('sku')->paginate(40)->withQueryString();

        return view('items.index', [
            'title' => 'Productos', 'items' => $items, 'settings' => $settings, 'stock' => $stock, 'q' => $q, 'cls' => $cls, 'inactive' => $inactive,
            'uoms' => Uom::orderBy('code')->get(),
            'kpis' => [
                'active' => Item::where('is_active', true)->count(), 'inactive' => Item::where('is_active', false)->count(),
                'noParams' => Item::where('is_active', true)->whereNotIn('id', $settings->whereNotNull('abc_class')->keys())->count(),
                'expiry' => Item::where('is_active', true)->where('tracks_expiry', true)->count(),
            ],
        ]);
    }

    public function show(Request $request, Item $item): View
    {
        $wh = $request->attributes->get('warehouse');
        $item->load(['baseUom', 'barcodes.uom']);

        return view('items.show', [
            'title' => $item->sku, 'item' => $item,
            'setting' => ItemWarehouse::where('warehouse_id', $wh->id)->where('item_id', $item->id)->with('defaultLocation')->first(),
            'balances' => StockBalance::forWarehouse($wh->id)->where('item_id', $item->id)->where('qty', '>', 0)->with(['location.zone', 'lot'])->get(),
            'movements' => StockMovement::forWarehouse($wh->id)->where('item_id', $item->id)->with(['user', 'fromLocation', 'toLocation'])->latest('occurred_at')->take(15)->get(),
            'out30' => (float) StockMovement::forWarehouse($wh->id)->where('item_id', $item->id)->whereIn('type', ['PICKING', 'DESPACHO'])->where('occurred_at', '>=', now()->subDays(30))->sum('qty'),
            'locations' => Location::forWarehouse($wh->id)->active()->orderBy('sort_seq')->get(['id', 'code']),
            'uoms' => Uom::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:40', 'unique:items,sku'],
            'name' => ['required', 'string', 'max:200'],
            'base_uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'category' => ['nullable', 'string', 'max:60'], 'subcategory' => ['nullable', 'string', 'max:60'],
            'factory_code' => ['nullable', 'string', 'max:40'],
            'tracks_lot' => ['nullable', 'boolean'], 'tracks_expiry' => ['nullable', 'boolean'],
            'shelf_life_days' => ['nullable', 'integer', 'min:0'], 'weight_kg' => ['nullable', 'numeric', 'min:0'], 'price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'], 'max_stock' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['tracks_lot'] = (bool) ($data['tracks_lot'] ?? false);
        $data['tracks_expiry'] = (bool) ($data['tracks_expiry'] ?? false);
        $data['is_active'] = true;

        $item = DB::transaction(function () use ($data) {
            $item = Item::create($data);
            ItemBarcode::create(['item_id' => $item->id, 'barcode' => $item->sku, 'uom_id' => $item->base_uom_id, 'qty_per_scan' => 1, 'type' => 'INTERNO']);

            return $item;
        });

        return redirect()->route('items.show', $item)->with('toast', "Producto {$item->sku} creado.");
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $wh = $request->attributes->get('warehouse');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:60'], 'subcategory' => ['nullable', 'string', 'max:60'], 'factory_code' => ['nullable', 'string', 'max:40'],
            'tracks_lot' => ['nullable', 'boolean'], 'tracks_expiry' => ['nullable', 'boolean'], 'shelf_life_days' => ['nullable', 'integer', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'], 'price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'], 'max_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            // parámetros del almacén de trabajo
            'abc_class' => ['nullable', 'in:A,B,C'], 'wh_min' => ['nullable', 'numeric', 'min:0'], 'wh_max' => ['nullable', 'numeric', 'min:0'],
            'default_location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $item->fill(array_merge($data, ['tracks_lot' => (bool) ($data['tracks_lot'] ?? false), 'tracks_expiry' => (bool) ($data['tracks_expiry'] ?? false), 'is_active' => (bool) ($data['is_active'] ?? false)]))->save();

        ItemWarehouse::updateOrCreate(
            ['item_id' => $item->id, 'warehouse_id' => $wh->id],
            ['abc_class' => $data['abc_class'] ?? null, 'min_stock' => $data['wh_min'] ?? null, 'max_stock' => $data['wh_max'] ?? null, 'default_location_id' => $data['default_location_id'] ?? null],
        );

        return back()->with('toast', 'Parámetros guardados.');
    }
}
