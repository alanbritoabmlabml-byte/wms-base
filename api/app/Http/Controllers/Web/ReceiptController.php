<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\Location;
use App\Models\Receipt;
use App\Models\ReceiptLine;
use App\Support\NavCounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $status = $request->query('estado');
        $type = $request->query('tipo');
        $q = trim((string) $request->query('q', ''));

        $receipts = Receipt::forWarehouse($wh->id)
            ->when($status, fn ($x) => $x->where('status', $status))
            ->when($type, fn ($x) => $x->where('type', $type))
            ->when($q !== '', fn ($x) => $x->where(fn ($y) => $y->where('number', 'like', "%$q%")->orWhere('external_ref', 'like', "%$q%")->orWhere('supplier_name', 'like', "%$q%")))
            ->withCount(['lines as lines_total', 'lines as lines_done' => fn ($x) => $x->whereIn('status', ['COMPLETA', 'EXCEDIDA'])])
            ->withSum('lines as qty_expected', 'qty_expected')
            ->withSum('lines as qty_received', 'qty_received')
            ->orderByRaw("FIELD(status, 'EN_PROCESO', 'ABIERTA', 'CERRADA', 'ANULADA')")
            ->latest('id')
            ->paginate(25)->withQueryString();

        $counts = Receipt::forWarehouse($wh->id)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('receipts.index', [
            'title' => 'Ingresos', 'receipts' => $receipts, 'counts' => $counts,
            'status' => $status, 'type' => $type, 'q' => $q,
        ]);
    }

    public function create(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');

        return view('receipts.create', [
            'title' => 'Nueva orden de ingreso',
            'items' => Item::active()->orderBy('sku')->get(['id', 'sku', 'name', 'base_uom_id', 'tracks_lot']),
            'docks' => Location::forWarehouse($wh->id)->active()->whereHas('zone', fn ($q) => $q->where('type', 'RECEPCION'))->orderBy('sort_seq')->get(),
            'nextNumber' => $this->nextNumber($wh->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $wh = $request->attributes->get('warehouse');

        $data = $request->validate([
            'number' => ['required', 'string', 'max:40'],
            'type' => ['required', 'in:PRODUCCION,COMPRA,DEVOLUCION,TRASPASO'],
            'external_ref' => ['nullable', 'string', 'max:60'],
            'supplier_name' => ['nullable', 'string', 'max:160'],
            'expected_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.qty_expected' => ['required', 'numeric', 'gt:0'],
            'lines.*.lot_code' => ['nullable', 'string', 'max:40'],
        ], [], ['lines' => 'líneas', 'lines.*.item_id' => 'producto', 'lines.*.qty_expected' => 'cantidad']);

        $receipt = DB::transaction(function () use ($data, $wh, $request) {
            $receipt = Receipt::create([
                'warehouse_id' => $wh->id,
                'number' => $data['number'],
                'type' => $data['type'],
                'status' => 'ABIERTA',
                'external_ref' => $data['external_ref'] ?? null,
                'supplier_name' => $data['supplier_name'] ?? null,
                'expected_at' => $data['expected_at'] ?? today(),
            ]);

            foreach (array_values($data['lines']) as $i => $line) {
                $item = Item::findOrFail($line['item_id']);
                ReceiptLine::create([
                    'receipt_id' => $receipt->id,
                    'line_no' => $i + 1,
                    'item_id' => $item->id,
                    'lot_code' => $line['lot_code'] ?: null,
                    'qty_expected' => $line['qty_expected'],
                    'qty_received' => 0,
                    'uom_id' => $item->base_uom_id,
                    'status' => 'PENDIENTE',
                ]);
            }

            AuditLog::create(['user_id' => $request->user()->id, 'model' => Receipt::class, 'model_id' => $receipt->id, 'action' => 'CREACION_WEB', 'after' => $receipt->only(['number', 'type']), 'ip' => $request->ip()]);

            return $receipt;
        });

        NavCounts::forget($wh->id);

        return redirect()->route('receipts.show', $receipt)->with('toast', "Orden {$receipt->number} habilitada. Ya aparece en los colectores del muelle.");
    }

    public function show(Request $request, Receipt $receipt): View
    {
        $this->authorizeWarehouse($request, $receipt->warehouse_id);
        $receipt->load(['lines.item.baseUom', 'lines.scans.location', 'lines.scans.user']);

        return view('receipts.show', [
            'title' => 'Orden '.$receipt->number, 'receipt' => $receipt,
            'audit' => AuditLog::where('model', Receipt::class)->where('model_id', $receipt->id)->with('user')->latest()->take(10)->get(),
        ]);
    }

    public function close(Request $request, Receipt $receipt): RedirectResponse
    {
        $this->authorizeWarehouse($request, $receipt->warehouse_id);
        abort_unless(in_array($receipt->status, Receipt::OPEN_STATUSES, true), 422, 'La orden ya no está abierta.');

        $pending = $receipt->lines()->whereNotIn('status', ['COMPLETA', 'EXCEDIDA'])->pluck('id')->all();
        $reason = $request->input('reason');

        if ($pending && ! $reason) {
            return back()->withErrors(['reason' => 'Hay líneas pendientes: indica el motivo del cierre con diferencias.']);
        }

        $before = $receipt->only(['status', 'closed_at', 'closed_by']);
        $receipt->forceFill(['status' => 'CERRADA', 'closed_at' => now(), 'closed_by' => $request->user()->id])->save();

        AuditLog::create([
            'user_id' => $request->user()->id, 'model' => Receipt::class, 'model_id' => $receipt->id,
            'action' => $pending ? 'CIERRE_FORZADO' : 'CIERRE', 'before' => $before,
            'after' => ['status' => 'CERRADA', 'reason' => $reason, 'pending_line_ids' => $pending], 'ip' => $request->ip(),
        ]);
        NavCounts::forget($receipt->warehouse_id);

        return back()->with('toast', $pending ? 'Orden cerrada con diferencias registradas a tu nombre.' : 'Orden cerrada.');
    }

    public function cancel(Request $request, Receipt $receipt): RedirectResponse
    {
        $this->authorizeWarehouse($request, $receipt->warehouse_id);
        abort_unless($receipt->status === 'ABIERTA' && (float) $receipt->lines()->sum('qty_received') == 0.0, 422, 'Solo se anulan órdenes sin recepciones.');

        $receipt->forceFill(['status' => 'ANULADA'])->save();
        AuditLog::create(['user_id' => $request->user()->id, 'model' => Receipt::class, 'model_id' => $receipt->id, 'action' => 'ANULACION', 'after' => ['reason' => $request->input('reason')], 'ip' => $request->ip()]);
        NavCounts::forget($receipt->warehouse_id);

        return redirect()->route('receipts.index')->with('toast', "Orden {$receipt->number} anulada.")->with('toast_kind', 'bad');
    }

    private function authorizeWarehouse(Request $request, int $warehouseId): void
    {
        abort_unless($request->attributes->get('warehouse')?->id === $warehouseId, 404);
    }

    private function nextNumber(int $warehouseId): string
    {
        $prefix = 'ING-'.now()->format('ym');
        $last = Receipt::where('warehouse_id', $warehouseId)->where('number', 'like', "$prefix%")->orderByDesc('number')->value('number');
        $n = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
