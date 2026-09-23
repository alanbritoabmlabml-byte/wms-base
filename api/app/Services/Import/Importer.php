<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\ImportBatch;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemWarehouse;
use App\Models\Location;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Valida y aplica una carga masiva. Cada fila se traduce con el mapeo del lote
 * (campo destino => encabezado del archivo) y se valida antes de tocar la BD.
 */
class Importer
{
    /** @return array{rows: array, errors: array} filas traducidas y errores por fila */
    public function validate(ImportBatch $batch, ?int $warehouseId): array
    {
        $def = Datasets::get($batch->dataset);
        $data = CsvReader::read(Storage::path($batch->stored_path));
        $mapping = $batch->mapping ?? [];
        $rows = [];
        $errors = [];
        $seen = [];

        foreach ($data['rows'] as $i => $raw) {
            $line = $i + 2;
            $row = [];
            foreach ($def['fields'] as $field => $required) {
                $src = $mapping[$field] ?? null;
                $row[$field] = $src !== null && $src !== '' ? ($raw[$src] ?? '') : '';
            }
            $rowErrors = [];
            foreach (Datasets::required($batch->dataset) as $field) {
                if ($row[$field] === '') {
                    $rowErrors[] = "$field vacío";
                }
            }
            $key = $row[$def['key']] ?? '';
            if ($key !== '') {
                if (isset($seen[$key])) {
                    $rowErrors[] = $def['key'].' duplicado (fila '.$seen[$key].')';
                }
                $seen[$key] = $line;
            }
            $rowErrors = array_merge($rowErrors, $this->validateRow($batch->dataset, $row, $warehouseId));

            $rows[] = $row;
            if ($rowErrors) {
                $errors[] = ['line' => $line, 'key' => $key, 'errors' => $rowErrors, 'row' => array_slice($row, 0, 4)];
            }
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    private function validateRow(string $dataset, array $r, ?int $warehouseId): array
    {
        $e = [];
        $bool = fn ($v) => $v === '' || in_array(mb_strtolower($v), ['1', '0', 'si', 'sí', 'no', 'true', 'false', 's', 'n'], true);
        $num = fn ($v) => $v === '' || is_numeric(str_replace(',', '.', $v));

        switch ($dataset) {
            case 'items':
                if ($r['um'] !== '' && ! Uom::where('code', strtoupper($r['um']))->exists()) {
                    $e[] = 'UM desconocida: '.$r['um'];
                }
                if ($r['clase_abc'] !== '' && ! in_array(strtoupper($r['clase_abc']), ['A', 'B', 'C'], true)) {
                    $e[] = 'clase_abc debe ser A, B o C';
                }
                foreach (['minimo', 'maximo', 'vida_util_dias', 'peso_kg', 'precio'] as $f) {
                    if (! $num($r[$f])) {
                        $e[] = "$f no numérico";
                    }
                }
                foreach (['maneja_lote', 'maneja_vencimiento'] as $f) {
                    if (! $bool($r[$f])) {
                        $e[] = "$f debe ser 1/0";
                    }
                }
                break;
            case 'locations':
                if ($r['tipo_zona'] !== '' && ! in_array(strtoupper($r['tipo_zona']), ['ALMACENAJE', 'PICKING', 'RECEPCION', 'DESPACHO', 'CUARENTENA', 'DEVOLUCION'], true)) {
                    $e[] = 'tipo_zona inválido';
                }
                if ($warehouseId && Location::onlyTrashed()->where('warehouse_id', $warehouseId)->where('code', strtoupper($r['codigo']))->exists()) {
                    $e[] = 'código de ubicación eliminado: no se reutiliza';
                }
                break;
            case 'vehicles':
                if ($r['chofer_ci'] !== '' && ! Driver::where('document_id', $r['chofer_ci'])->exists()) {
                    $e[] = 'chofer_ci no existe';
                }
                break;
            case 'users_collector':
            case 'users_desktop':
                if (! in_array(strtoupper($r['rol']), ['ADMIN', 'SUPERVISOR', 'OPERADOR'], true)) {
                    $e[] = 'rol debe ser ADMIN, SUPERVISOR u OPERADOR';
                }
                $whs = $dataset === 'users_desktop' ? preg_split('/[|;]/', $r['almacenes']) : [$r['almacen']];
                foreach (array_filter(array_map('trim', $whs)) as $code) {
                    if (! Warehouse::where('code', $code)->exists()) {
                        $e[] = "almacén $code no existe";
                    }
                }
                if ($dataset === 'users_collector' && $r['pin'] !== '' && ! preg_match('/^\d{4,6}$/', $r['pin'])) {
                    $e[] = 'pin debe tener 4 a 6 dígitos';
                }
                break;
        }

        return $e;
    }

    /** Aplica las filas válidas. Devuelve [creados, actualizados]. */
    public function commit(ImportBatch $batch, array $rows, array $errorLines, ?int $warehouseId, int $userId): array
    {
        $bad = array_flip(array_map(fn ($e) => $e['line'], $errorLines));
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($batch, $rows, $bad, $warehouseId, &$created, &$updated) {
            foreach ($rows as $i => $r) {
                if (isset($bad[$i + 2])) {
                    continue;
                }
                $isNew = $this->apply($batch->dataset, $batch->mode, $r, $warehouseId);
                if ($isNew === null) {
                    continue;
                }
                $isNew ? $created++ : $updated++;
            }
        });

        return [$created, $updated];
    }

    /** @return bool|null true creado, false actualizado, null omitido */
    private function apply(string $dataset, string $mode, array $r, ?int $warehouseId): ?bool
    {
        $b = fn ($v, $d = false) => $v === '' ? $d : in_array(mb_strtolower($v), ['1', 'si', 'sí', 'true', 's'], true);
        $n = fn ($v) => $v === '' ? null : (float) str_replace(',', '.', $v);
        $active = fn ($v) => $v === '' || in_array(mb_strtoupper($v), ['ACTIVO', '1', 'HABILITADO', 'A'], true);

        switch ($dataset) {
            case 'items':
                $item = Item::withTrashed()->where('sku', $r['sku'])->first();
                if ($item && $mode === 'INSERT') {
                    return null;
                }
                $uom = Uom::where('code', strtoupper($r['um']))->firstOrFail();
                $attrs = array_filter([
                    'name' => $r['descripcion'], 'base_uom_id' => $uom->id, 'category' => $r['categoria'] ?: null, 'subcategory' => $r['subcategoria'] ?: null,
                    'factory_code' => $r['cod_fabrica'] ?: null, 'min_stock' => $n($r['minimo']), 'max_stock' => $n($r['maximo']), 'shelf_life_days' => $n($r['vida_util_dias']),
                    'weight_kg' => $n($r['peso_kg']), 'price' => $n($r['precio']), 'is_active' => $active($r['estado']),
                ], fn ($v) => $v !== null);
                $attrs['tracks_lot'] = $b($r['maneja_lote'], $item?->tracks_lot ?? true);
                $attrs['tracks_expiry'] = $b($r['maneja_vencimiento'], $item?->tracks_expiry ?? false);
                if ($item) {
                    $item->restore();
                    $item->fill($attrs)->save();
                } else {
                    $item = Item::create($attrs + ['sku' => $r['sku']]);
                    ItemBarcode::firstOrCreate(['barcode' => $item->sku], ['item_id' => $item->id, 'uom_id' => $uom->id, 'qty_per_scan' => 1, 'type' => 'INTERNO']);
                }
                if ($warehouseId && ($r['clase_abc'] !== '' || $r['minimo'] !== '' || $r['maximo'] !== '')) {
                    ItemWarehouse::updateOrCreate(['item_id' => $item->id, 'warehouse_id' => $warehouseId], array_filter([
                        'abc_class' => $r['clase_abc'] !== '' ? strtoupper($r['clase_abc']) : null, 'min_stock' => $n($r['minimo']), 'max_stock' => $n($r['maximo']),
                    ], fn ($v) => $v !== null));
                }

                return $item->wasRecentlyCreated;

            case 'customers':
                $c = Customer::withTrashed()->where('code', $r['codigo'])->first();
                if ($c && $mode === 'INSERT') {
                    return null;
                }
                $attrs = ['name' => $r['razon_social'], 'tax_id' => $r['nit'] ?: null, 'contact_name' => $r['contacto'] ?: null, 'phone' => $r['telefono'] ?: null, 'address' => $r['direccion'] ?: null,
                    'department' => $r['departamento'] ?: null, 'province' => $r['provincia'] ?: null, 'city' => $r['ciudad'] ?: null, 'zone' => $r['zona'] ?: null, 'channel' => $r['canal'] ?: null, 'is_active' => $active($r['estado'])];
                if ($c) {
                    $c->restore();
                    $c->fill($attrs)->save();

                    return false;
                }
                Customer::create($attrs + ['code' => $r['codigo']]);

                return true;

            case 'locations':
                if (! $warehouseId) {
                    return null;
                }
                $zoneCode = strtoupper($r['rack']);
                $zone = Zone::firstOrCreate(['warehouse_id' => $warehouseId, 'code' => $zoneCode], ['name' => 'Rack '.$zoneCode, 'type' => $r['tipo_zona'] !== '' ? strtoupper($r['tipo_zona']) : 'ALMACENAJE']);
                $code = strtoupper($r['codigo']);
                $loc = Location::where('warehouse_id', $warehouseId)->where('code', $code)->first();
                if ($loc && $mode === 'INSERT') {
                    return null;
                }
                $attrs = ['zone_id' => $zone->id, 'barcode' => $code, 'rack' => $zoneCode, 'position' => $r['columna'] !== '' ? str_pad($r['columna'], 2, '0', STR_PAD_LEFT) : null, 'level' => $r['nivel'] ?: null,
                    'sort_seq' => $r['orden'] !== '' ? (int) $r['orden'] : (((int) Location::where('warehouse_id', $warehouseId)->max('sort_seq')) + 1),
                    'max_weight_kg' => $n($r['peso_max_kg']), 'is_mixing_allowed' => $b($r['permite_mezcla'], true), 'is_active' => $active($r['estado'])];
                if ($loc) {
                    $loc->fill($attrs)->save();

                    return false;
                }
                Location::create($attrs + ['warehouse_id' => $warehouseId, 'code' => $code]);

                return true;

            case 'drivers':
                $d = Driver::withTrashed()->where('document_id', $r['ci'])->first();
                if ($d && $mode === 'INSERT') {
                    return null;
                }
                $attrs = ['first_name' => $r['nombre'], 'last_name' => $r['apellidos'], 'license_category' => $r['licencia'] ?: null, 'phone' => $r['telefono'] ?: null, 'address' => $r['direccion'] ?: null,
                    'status' => in_array(strtoupper($r['estado']), ['ACTIVO', 'EN_RUTA', 'INACTIVO'], true) ? strtoupper($r['estado']) : 'ACTIVO'];
                if ($d) {
                    $d->restore();
                    $d->fill($attrs)->save();

                    return false;
                }
                Driver::create($attrs + ['document_id' => $r['ci']]);

                return true;

            case 'vehicles':
                $plate = strtoupper($r['placa']);
                $v = Vehicle::withTrashed()->where('plate', $plate)->first();
                if ($v && $mode === 'INSERT') {
                    return null;
                }
                $attrs = ['type' => $r['tipo'], 'brand' => $r['marca'] ?: null, 'model' => $r['modelo'] ?: null, 'capacity_kg' => $n($r['capacidad_kg']), 'volume_m3' => $n($r['volumen_m3']),
                    'driver_id' => $r['chofer_ci'] !== '' ? Driver::where('document_id', $r['chofer_ci'])->value('id') : null,
                    'status' => in_array(strtoupper($r['estado']), ['DISPONIBLE', 'EN_RUTA', 'MANTENIMIENTO', 'INACTIVO'], true) ? strtoupper($r['estado']) : 'DISPONIBLE'];
                if ($v) {
                    $v->restore();
                    $v->fill($attrs)->save();

                    return false;
                }
                Vehicle::create($attrs + ['plate' => $plate]);

                return true;

            case 'users_collector':
            case 'users_desktop':
                $desktop = $dataset === 'users_desktop';
                $user = User::where('username', $r['usuario'])->first();
                if ($user && $mode === 'INSERT') {
                    return null;
                }
                $attrs = ['name' => $r['nombre'], 'is_active' => $active($r['estado']), 'document_id' => $desktop ? null : ($r['documento'] ?: null)];
                if ($desktop && $r['correo'] !== '') {
                    $attrs['email'] = $r['correo'];
                }
                if ($user) {
                    $attrs['client_type'] = $user->client_type === 'AMBOS' || $user->client_type === ($desktop ? 'ESCRITORIO' : 'COLECTOR') ? $user->client_type : 'AMBOS';
                    if ($desktop && $r['password'] !== '') {
                        $attrs['password'] = $r['password'];
                    }
                    if (! $desktop && $r['pin'] !== '') {
                        $attrs['pin'] = $r['pin'];
                    }
                    $user->fill($attrs)->save();
                    $new = false;
                } else {
                    $attrs['username'] = $r['usuario'];
                    $attrs['client_type'] = $desktop ? 'ESCRITORIO' : 'COLECTOR';
                    $attrs['password'] = $desktop ? ($r['password'] ?: Str::random(12)) : Str::random(24);
                    $attrs['pin'] = $desktop ? null : ($r['pin'] ?: str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT));
                    $user = User::create($attrs);
                    $new = true;
                }
                $codes = $desktop ? preg_split('/[|;]/', $r['almacenes']) : [$r['almacen']];
                $role = strtoupper($r['rol']);
                foreach (array_filter(array_map('trim', $codes)) as $code) {
                    $wid = Warehouse::where('code', $code)->value('id');
                    if ($wid) {
                        $user->warehouses()->syncWithoutDetaching([$wid => ['role' => $role]]);
                        $user->warehouses()->updateExistingPivot($wid, ['role' => $role]);
                    }
                }

                return $new;
        }

        return null;
    }
}
