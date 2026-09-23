<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Setting;
use App\Models\StockBalance;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Mapa de almacén: sucursal → almacén → zona (rack/área) → ubicación.
 * Las ubicaciones se crean solo aquí y nunca se reutilizan (soft delete).
 */
class MapController extends Controller
{
    public function index(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $zones = $wh->zones()->withCount('locations')->orderBy('type')->orderBy('code')->get();
        $zone = $zones->firstWhere('id', (int) $request->query('zona')) ?? $zones->first();

        $locations = $zone ? Location::where('zone_id', $zone->id)->withSum(['balances as qty' => fn ($q) => $q->where('qty', '>', 0)], 'qty')->orderBy('sort_seq')->get() : collect();
        $selected = $request->query('ubicacion') ? $locations->firstWhere('id', (int) $request->query('ubicacion')) : null;
        $selectedStock = $selected ? StockBalance::where('location_id', $selected->id)->where('qty', '>', 0)->with(['item', 'lot'])->get() : collect();

        // Grilla: niveles (level) × posiciones (position). Si la zona no tiene
        // filas/columnas numéricas, se muestra como lista.
        $levels = $locations->pluck('level')->filter()->unique()->sortDesc(SORT_NATURAL)->values();
        $positions = $locations->pluck('position')->filter()->unique()->sort(SORT_NATURAL)->values();
        $grid = $levels->count() && $positions->count() && $levels->count() * $positions->count() <= 400;
        $capacity = (float) Setting::get('codigos', 'capacidad_por_ubicacion', 6, $wh->id) * 20;

        return view('map.index', [
            'title' => 'Mapa de almacén', 'zones' => $zones, 'zone' => $zone, 'locations' => $locations,
            'selected' => $selected, 'selectedStock' => $selectedStock,
            'levels' => $levels, 'positions' => $positions, 'grid' => $grid, 'capacity' => $capacity,
            'occupied' => $locations->where('qty', '>', 0)->count(),
            'format' => Setting::get('codigos', 'formato_ubicacion', '{RACK}-C{COL:2}-N{NIVEL}', $wh->id),
        ]);
    }

    public function storeRack(Request $request): RedirectResponse
    {
        $wh = $request->attributes->get('warehouse');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/i'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:ALMACENAJE,PICKING,RECEPCION,DESPACHO,CUARENTENA,DEVOLUCION'],
            'levels' => ['required', 'integer', 'min:1', 'max:20'],
            'positions' => ['required', 'integer', 'min:1', 'max:60'],
            'picking_priority' => ['nullable', 'integer', 'min:1', 'max:999'],
            'mixing' => ['nullable', 'boolean'],
        ], [], ['code' => 'código', 'levels' => 'niveles', 'positions' => 'columnas']);

        $code = strtoupper($data['code']);
        abort_if(Zone::where('warehouse_id', $wh->id)->where('code', $code)->exists(), 422, "Ya existe el rack $code.");

        $zone = DB::transaction(function () use ($data, $code, $wh, $request) {
            $zone = Zone::create(['warehouse_id' => $wh->id, 'code' => $code, 'name' => $data['name'], 'type' => $data['type'], 'picking_priority' => $data['picking_priority'] ?? 100]);
            $format = Setting::get('codigos', 'formato_ubicacion', '{RACK}-C{COL:2}-N{NIVEL}', $wh->id);
            $baseSeq = ((int) Location::where('warehouse_id', $wh->id)->max('sort_seq')) + 1;
            $seq = 0;

            // Recorrido en serpentina por columna: C01 N1→N4, C02 N4→N1, …
            for ($c = 1; $c <= $data['positions']; $c++) {
                $levels = range(1, $data['levels']);
                if ($c % 2 === 0) {
                    $levels = array_reverse($levels);
                }
                foreach ($levels as $l) {
                    $locCode = self::formatCode($format, $code, $c, $l);
                    Location::create([
                        'warehouse_id' => $wh->id, 'zone_id' => $zone->id, 'code' => $locCode, 'barcode' => $locCode,
                        'rack' => $code, 'level' => (string) $l, 'position' => str_pad((string) $c, 2, '0', STR_PAD_LEFT),
                        'sort_seq' => $baseSeq + $seq++, 'is_mixing_allowed' => (bool) ($data['mixing'] ?? true), 'is_active' => true,
                    ]);
                }
            }
            AuditLog::create(['user_id' => $request->user()->id, 'model' => Zone::class, 'model_id' => $zone->id, 'action' => 'CREACION', 'after' => ['code' => $code, 'locations' => $seq], 'ip' => $request->ip()]);

            return $zone;
        });

        $n = $data['levels'] * $data['positions'];

        return redirect()->route('map.index', ['zona' => $zone->id])->with('toast', "Rack $code creado con $n ubicaciones. Etiquetas listas para imprimir.");
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $wh = $request->attributes->get('warehouse');
        $data = $request->validate([
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'code' => ['required', 'string', 'max:30'],
            'level' => ['nullable', 'string', 'max:10'],
            'position' => ['nullable', 'string', 'max:10'],
            'mixing' => ['nullable', 'boolean'],
        ]);
        $zone = Zone::findOrFail($data['zone_id']);
        abort_unless($zone->warehouse_id === $wh->id, 404);
        $code = strtoupper(trim($data['code']));
        abort_if(Location::withTrashed()->where('warehouse_id', $wh->id)->where('code', $code)->exists(), 422, "El código $code ya se usó en este almacén (aunque esté eliminado no se reutiliza).");

        $loc = Location::create([
            'warehouse_id' => $wh->id, 'zone_id' => $zone->id, 'code' => $code, 'barcode' => $code, 'rack' => $zone->code,
            'level' => $data['level'] ?: null, 'position' => $data['position'] ?: null,
            'sort_seq' => ((int) Location::where('warehouse_id', $wh->id)->max('sort_seq')) + 1,
            'is_mixing_allowed' => (bool) ($data['mixing'] ?? true), 'is_active' => true,
        ]);

        return redirect()->route('map.index', ['zona' => $zone->id, 'ubicacion' => $loc->id])->with('toast', "Ubicación $code creada.");
    }

    public function toggleLocation(Request $request, Location $location): RedirectResponse
    {
        abort_unless($location->warehouse_id === $request->attributes->get('warehouse')?->id, 404);
        $location->forceFill(['is_active' => ! $location->is_active])->save();
        AuditLog::create(['user_id' => $request->user()->id, 'model' => Location::class, 'model_id' => $location->id, 'action' => $location->is_active ? 'DESBLOQUEO' : 'BLOQUEO', 'ip' => $request->ip()]);

        return back()->with('toast', "Ubicación {$location->code} ".($location->is_active ? 'desbloqueada.' : 'bloqueada para picking.'));
    }

    public static function formatCode(string $format, string $rack, int $col, int $level): string
    {
        return preg_replace_callback('/\{(RACK|COL|NIVEL)(?::(\d+))?\}/', function ($m) use ($rack, $col, $level) {
            $v = match ($m[1]) { 'RACK' => $rack, 'COL' => (string) $col, 'NIVEL' => (string) $level };

            return isset($m[2]) && $m[1] !== 'RACK' ? str_pad($v, (int) $m[2], '0', STR_PAD_LEFT) : $v;
        }, $format);
    }
}
