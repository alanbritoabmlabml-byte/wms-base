<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LabelTemplate;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Editor de plantillas de etiqueta (QR + Code128). La vista previa se dibuja en
 * el navegador con los mismos parámetros que se envían a la impresora.
 */
class LabelTemplateController extends Controller
{
    public const PRINTERS_DEFAULT = ['Zebra ZD421 · Almacén', 'Zebra ZT411 · Muelle ingreso', 'Zebra ZQ630 (Bluetooth) · Colector', 'PDF · hoja A4 con 8 etiquetas'];

    public function index(Request $request): View
    {
        $this->ensureDefaults();

        $order = array_flip(['UBICACION', 'ITEM', 'PALLET', 'DESPACHO']);
        $templates = LabelTemplate::all()->sortBy(fn ($t) => ($order[$t->code] ?? 9).$t->code)->values();
        $tpl = $templates->firstWhere('code', strtoupper((string) $request->query('tpl'))) ?? $templates->first();
        $wh = $request->attributes->get('warehouse');

        return view('config.labels', [
            'title' => 'Etiquetas QR',
            'templates' => $templates,
            'tpl' => $tpl,
            'samples' => $this->samplesFor($tpl, $wh, (string) $request->query('q', '')),
            'printers' => Setting::get('etiquetas', 'impresoras', self::PRINTERS_DEFAULT, $wh->id),
            'fieldLabels' => self::fieldLabels(),
            'company' => Setting::get('empresa', 'nombre', config('app.name'), $wh->id),
        ]);
    }

    public function update(Request $request, LabelTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'width_mm' => ['required', 'integer', 'min:20', 'max:210'],
            'height_mm' => ['required', 'integer', 'min:15', 'max:297'],
            'qr_format' => ['required', 'in:PLAIN,GS1,JSON,URL'],
            'qr_size' => ['required', 'integer', 'min:1', 'max:4'],
            'font_scale' => ['required', 'integer', 'min:60', 'max:160'],
            'printer' => ['nullable', 'string', 'max:80'],
            'fields' => ['nullable', 'array'],
        ]);

        $fields = [];
        foreach (LabelTemplate::FIELDS as $f) {
            $fields[$f] = (bool) ($data['fields'][$f] ?? false);
        }

        $template->fill($data + ['fields' => $fields])->forceFill([
            'fields' => $fields,
            'version' => $template->version + 1,
            'updated_by' => $request->user()->id,
        ])->save();

        return redirect()->route('config.labels', ['tpl' => $template->code])
            ->with('toast', "Plantilla «{$template->name}» guardada como versión {$template->version}. Los colectores la usan desde ahora.");
    }

    /** Muestras para la vista previa (búsqueda por código). */
    public function samples(Request $request, LabelTemplate $template): JsonResponse
    {
        return response()->json($this->samplesFor($template, $request->attributes->get('warehouse'), (string) $request->query('q', '')));
    }

    // -----------------------------------------------------------------

    private function ensureDefaults(): void
    {
        if (LabelTemplate::count() > 0) {
            return;
        }
        foreach (LabelTemplate::defaults() as $d) {
            LabelTemplate::create($d + ['qr_size' => 3, 'font_scale' => 100, 'version' => 1]);
        }
    }

    public static function fieldLabels(): array
    {
        return [
            'qr' => 'Código QR', 'code128' => 'Code 128 (lectores 1D)', 'title' => 'Código en grande', 'description' => 'Descripción y detalle',
            'logo' => 'Logo y almacén', 'stripe' => 'Franja de color', 'lot' => 'Lote', 'expiry' => 'Vencimiento', 'qty' => 'Cantidad', 'date' => 'Fecha',
        ];
    }

    /**
     * Devuelve hasta 12 registros reales del almacén con la forma que consume el
     * dibujador: code, title, desc, sub, lot, expiry, qty, date, kind.
     */
    private function samplesFor(LabelTemplate $tpl, Warehouse $wh, string $q = ''): array
    {
        $q = trim($q);
        $out = [];

        switch ($tpl->code) {
            case 'UBICACION':
                $locs = Location::with('zone')->forWarehouse($wh->id)->active()
                    ->when($q !== '', fn ($x) => $x->where('code', 'like', "%$q%"))
                    ->orderBy('sort_seq')->orderBy('code')->limit(12)->get();
                foreach ($locs as $l) {
                    $out[] = ['kind' => 'loc', 'code' => $l->code, 'title' => $l->code,
                        'desc' => trim(($l->zone?->name ?? $l->rack ?? '').' · Col '.str_pad((string) $l->position, 2, '0', STR_PAD_LEFT).' · Nivel '.$l->level),
                        'sub' => $wh->name, 'lot' => '', 'expiry' => '', 'qty' => '', 'date' => ''];
                }
                break;

            case 'DESPACHO':
                $orders = SalesOrder::with('customer')->forWarehouse($wh->id)
                    ->when($q !== '', fn ($x) => $x->where('number', 'like', "%$q%"))
                    ->latest('ordered_at')->limit(12)->get();
                foreach ($orders as $o) {
                    $n = max(1, (int) $o->packages);
                    $out[] = ['kind' => 'ped', 'code' => $o->number, 'title' => $o->number, 'desc' => $o->customer?->name ?? '',
                        'sub' => trim(($o->customer?->city ?? '').($o->customer?->department ? ', '.$o->customer->department : '')),
                        'lot' => '', 'expiry' => '', 'qty' => "Bulto 1 de $n", 'date' => optional($o->ordered_at)->format('d/m/Y') ?? ''];
                }
                break;

            default: // ITEM y PALLET
                $rows = StockBalance::with(['item.baseUom', 'lot'])->forWarehouse($wh->id)->withStock()
                    ->when($q !== '', fn ($x) => $x->whereHas('item', fn ($i) => $i->where('sku', 'like', "%$q%")->orWhere('name', 'like', "%$q%")))
                    ->orderByDesc('last_movement_at')->limit(12)->get();
                foreach ($rows as $i => $b) {
                    $um = $b->item?->baseUom?->code ?? '';
                    $lot = $b->lot && $b->lot->code !== '-' ? $b->lot->code : '';
                    $base = ['desc' => $b->item?->name ?? '', 'lot' => $lot,
                        'expiry' => optional($b->lot?->expires_at)->format('d/m/Y') ?? '',
                        'date' => optional($b->last_movement_at)->format('d/m/Y') ?? ''];
                    if ($tpl->code === 'PALLET') {
                        $sscc = self::sscc($wh->id, $b->id);
                        $out[] = array_merge($base, ['kind' => 'pallet', 'code' => $sscc, 'title' => 'SSCC '.chunk_split($sscc, 6, ' '),
                            'sub' => $b->item?->sku ?? '', 'qty' => rtrim(rtrim(number_format((float) $b->qty * 4, 2, '.', ''), '0'), '.')." $um · 4 niveles", 'expiry' => '']);
                    } else {
                        $out[] = array_merge($base, ['kind' => 'prod', 'code' => $b->item?->sku ?? '', 'title' => $b->item?->sku ?? '',
                            'sub' => trim($um.($b->item?->subcategory ? ' · '.$b->item->subcategory : ($b->item?->category ? ' · '.$b->item->category : ''))),
                            'qty' => rtrim(rtrim(number_format((float) $b->qty, 2, '.', ''), '0'), '.')." $um"]);
                    }
                }
        }

        if (! $out) {
            $out[] = ['kind' => 'loc', 'code' => 'E1-C01-N1', 'title' => 'E1-C01-N1', 'desc' => 'Rack E1 · Col 01 · Nivel 1', 'sub' => $wh->name, 'lot' => '', 'expiry' => '', 'qty' => '', 'date' => ''];
        }

        return $out;
    }

    /** SSCC de 18 dígitos (AI 00) con dígito verificador GS1 mod-10. */
    public static function sscc(int $whId, int $serial): string
    {
        $body = '0'.str_pad('779'.str_pad((string) $whId, 4, '0', STR_PAD_LEFT), 7, '0').str_pad((string) ($serial % 1000000000), 9, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split(strrev($body)) as $i => $d) {
            $sum += (int) $d * ($i % 2 === 0 ? 3 : 1);
        }

        return $body.((10 - $sum % 10) % 10);
    }
}
