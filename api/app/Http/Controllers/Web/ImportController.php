<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\Import\CsvReader;
use App\Services\Import\Datasets;
use App\Services\Import\Importer;
use App\Support\NavCounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Carga masiva de maestros en 4 pasos: archivo → mapeo → validación → confirmación.
 * Los archivos se guardan en storage/app/private/imports y el lote queda como
 * historial (import_batches) aunque se descarte.
 */
class ImportController extends Controller
{
    public function __construct(private readonly Importer $importer) {}

    public function index(Request $request): View
    {
        $dataset = $request->query('dataset');
        $dataset = Datasets::get((string) $dataset) ? $dataset : 'items';

        $recent = ImportBatch::with('user')
            ->where('warehouse_id', $request->attributes->get('warehouse')->id)
            ->latest()->limit(12)->get();

        $open = $recent->firstWhere(fn ($b) => in_array($b->status, ['CARGADO', 'MAPEADO', 'VALIDADO'], true));

        return view('config.import', [
            'title' => 'Importar datos',
            'datasets' => Datasets::all(),
            'dataset' => $dataset,
            'recent' => $recent,
            'open' => $open,
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dataset' => ['required', 'string', 'in:'.implode(',', array_keys(Datasets::all()))],
            'mode' => ['required', 'in:UPSERT,INSERT,REPLACE'],
            'file' => ['required', 'file', 'max:20480', 'mimes:csv,txt'],
        ], [], ['file' => 'archivo']);

        $file = $request->file('file');
        $name = now()->format('Ymd_His').'_'.$data['dataset'].'.csv';
        $stored = Storage::putFileAs('imports', $file, $name);

        $parsed = CsvReader::read(Storage::path($stored), 5);
        if (! $parsed['headers']) {
            Storage::delete($stored);

            return back()->withErrors(['file' => 'El archivo está vacío o no tiene encabezados.'])->withInput();
        }

        $def = Datasets::get($data['dataset']);
        $batch = ImportBatch::create([
            'dataset' => $data['dataset'],
            'filename' => $file->getClientOriginalName(),
            'mode' => $data['mode'],
            'status' => 'MAPEADO',
            'rows_total' => max(0, count(preg_split('/\r\n|\r|\n/', trim(file_get_contents(Storage::path($stored))))) - 1),
            'headers' => $parsed['headers'],
            'mapping' => CsvReader::autoMap($parsed['headers'], array_keys($def['fields'])),
            'stored_path' => $stored,
            'warehouse_id' => $request->attributes->get('warehouse')->id,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('config.import.show', $batch)->with('toast', "Archivo {$batch->filename} cargado: {$batch->rows_total} filas.");
    }

    public function show(Request $request, ImportBatch $batch): View
    {
        $def = Datasets::get($batch->dataset);
        $preview = $batch->stored_path && Storage::exists($batch->stored_path) ? CsvReader::read(Storage::path($batch->stored_path), 6) : ['headers' => [], 'rows' => []];

        $step = match ($batch->status) {
            'CARGADO', 'MAPEADO' => 2,
            'VALIDADO' => 3,
            default => 4,
        };

        return view('config.import-show', [
            'title' => 'Importar · '.$def['label'],
            'batch' => $batch,
            'def' => $def,
            'required' => Datasets::required($batch->dataset),
            'preview' => $preview['rows'],
            'step' => $step,
            'errors_' => $batch->errors ?? [],
        ]);
    }

    /** Paso 2 → 3: guarda el mapeo y valida todas las filas. */
    public function map(Request $request, ImportBatch $batch): RedirectResponse
    {
        abort_unless(in_array($batch->status, ['CARGADO', 'MAPEADO', 'VALIDADO'], true), 409, 'El lote ya fue procesado.');

        $mapping = array_filter((array) $request->input('map', []), fn ($v) => $v !== null && $v !== '');

        if ($request->boolean('back')) {
            $batch->update(['mapping' => $mapping, 'status' => 'MAPEADO']);

            return redirect()->route('config.import.show', $batch);
        }

        $missing = array_diff(Datasets::required($batch->dataset), array_keys($mapping));
        if ($missing) {
            return back()->withErrors(['map' => 'Faltan columnas obligatorias: '.implode(', ', $missing)]);
        }

        $batch->mapping = $mapping;
        $result = $this->importer->validate($batch, $batch->warehouse_id);

        $batch->fill([
            'status' => 'VALIDADO',
            'rows_total' => count($result['rows']),
            'rows_error' => count($result['errors']),
            'rows_ok' => count($result['rows']) - count($result['errors']),
            'errors' => array_slice($result['errors'], 0, 200),
        ])->save();

        return redirect()->route('config.import.show', $batch);
    }

    /** Paso 3 → 4: aplica las filas válidas. */
    public function commit(Request $request, ImportBatch $batch): RedirectResponse
    {
        abort_unless($batch->status === 'VALIDADO', 409, 'Primero valida el archivo.');

        $result = $this->importer->validate($batch, $batch->warehouse_id);
        [$created, $updated] = $this->importer->commit($batch, $result['rows'], $result['errors'], $batch->warehouse_id, $request->user()->id);

        $batch->fill([
            'status' => 'CONFIRMADO',
            'rows_ok' => $created + $updated,
            'rows_error' => count($result['errors']),
            'errors' => array_slice($result['errors'], 0, 200),
        ])->save();

        NavCounts::forget($batch->warehouse_id);

        return redirect()->route('config.import.show', $batch)
            ->with('toast', "Importación confirmada: {$created} creados, {$updated} actualizados, ".count($result['errors']).' omitidos.');
    }

    public function discard(ImportBatch $batch): RedirectResponse
    {
        if ($batch->status !== 'CONFIRMADO') {
            $batch->update(['status' => 'DESCARTADO']);
            if ($batch->stored_path) {
                Storage::delete($batch->stored_path);
            }
        }

        return redirect()->route('config.import', ['dataset' => $batch->dataset])->with('toast', 'Lote descartado.');
    }

    /** Plantilla CSV con encabezados + 2 filas de ejemplo. */
    public function template(string $dataset): Response
    {
        $def = Datasets::get($dataset);
        abort_unless($def, 404);

        $csv = "\xEF\xBB\xBF".$def['sample']."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_'.$dataset.'.csv"',
        ]);
    }
}
