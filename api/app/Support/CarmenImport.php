<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importación masiva de Carmen WMS (plantillas Excel/CSV).
 *
 * Cada fila llega con los nombres de columna de la plantilla (codigo,
 * descripcion, um…). Se valida fila por fila; las filas con error se omiten y
 * se informan con su número de fila del archivo (encabezado = fila 1).
 */
final class CarmenImport
{
    /** dataset => etiqueta, campos (obligatorios con *) y columnas agrupadoras. */
    public const DATASETS = [
        'productos' => ['label' => 'Productos', 'fields' => ['codigo*', 'descripcion*', 'um*', 'categoria', 'subcategoria', 'cod_fabrica', 'minimo', 'maximo', 'clase_abc', 'vida_util_dias', 'peso_kg', 'precio', 'estado']],
        'clientes' => ['label' => 'Clientes', 'fields' => ['codigo*', 'razon_social*', 'nit', 'telefono', 'direccion', 'departamento', 'ciudad', 'zona', 'canal', 'estado']],
        'racks' => ['label' => 'Racks y áreas', 'fields' => ['codigo*', 'nombre*', 'niveles*', 'columnas*', 'tipo', 'zona', 'capacidad', 'almacen']],
        'ubicaciones' => ['label' => 'Ubicaciones', 'fields' => ['codigo*', 'rack*', 'columna*', 'nivel*', 'tipo', 'capacidad', 'sort_seq', 'estado', 'almacen']],
        'stock_inicial' => ['label' => 'Stock inicial', 'fields' => ['ubicacion*', 'codigo*', 'cantidad*', 'lote', 'fecha_ingreso', 'vencimiento', 'estado', 'almacen']],
        'ingresos' => ['label' => 'Órdenes de ingreso', 'fields' => ['nro*', 'tipo*', 'codigo*', 'cantidad*', 'fecha', 'documento', 'origen', 'muelle', 'lote', 'turno', 'estado', 'almacen']],
        'pedidos' => ['label' => 'Pedidos', 'fields' => ['nro*', 'cliente*', 'codigo*', 'cantidad*', 'fecha', 'prioridad', 'referencia', 'bultos', 'observacion', 'almacen']],
        'choferes' => ['label' => 'Choferes', 'fields' => ['ci*', 'nombre*', 'apellidos', 'licencia', 'telefono', 'estado']],
        'camiones' => ['label' => 'Camiones', 'fields' => ['placa*', 'tipo*', 'marca', 'modelo', 'capacidad_kg', 'volumen_m3', 'chofer_ci', 'estado']],
        'colectores' => ['label' => 'Colectores', 'fields' => ['codigo*', 'modelo*', 'sistema', 'usuario', 'version_app', 'almacen']],
        'usuarios_escritorio' => ['label' => 'Usuarios escritorio', 'fields' => ['usuario*', 'nombre*', 'rol*', 'almacenes*', 'correo', 'contrasena', 'estado']],
        'usuarios_colector' => ['label' => 'Usuarios colector', 'fields' => ['usuario*', 'nombre*', 'rol*', 'almacen*', 'documento', 'pin', 'equipo', 'estado']],
    ];

    private array $errors = [];

    private array $created = [];

    private int $ok = 0;

    public function __construct(private string $username, private string $wh) {}

    /** @return array{ok:int, errors:array, created:array} */
    public function run(string $dataset, array $rows, string $mode): array
    {
        $rows = array_values(array_map(fn ($r) => array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $r), $rows));
        $required = array_map(fn ($f) => rtrim($f, '*'), array_filter(self::DATASETS[$dataset]['fields'], fn ($f) => str_ends_with($f, '*')));

        // Validación de obligatorios.
        $valid = [];
        foreach ($rows as $i => $r) {
            $miss = array_filter($required, fn ($f) => ($r[$f] ?? '') === '' || $r[$f] === null);
            if ($miss) {
                $this->err($i, 'Falta '.implode(', ', $miss));

                continue;
            }
            $valid[$i] = $r;
        }

        DB::transaction(function () use ($dataset, $valid, $mode) {
            $this->{'import'.Str::studly($dataset)}($valid, $mode);
        });

        return ['ok' => $this->ok, 'errors' => $this->errors, 'created' => $this->created];
    }

    // ---------------------------------------------------------------- maestros

    private function importProductos(array $rows, string $mode): void
    {
        $keys = $this->uniqueRows($rows, 'codigo');
        foreach ($keys as $i => $r) {
            foreach (['minimo', 'maximo', 'vida_util_dias', 'peso_kg', 'precio'] as $n) {
                if (($r[$n] ?? '') !== '' && ! is_numeric(str_replace(',', '.', (string) $r[$n]))) {
                    $this->err($i, "$n no es numérico");

                    continue 2;
                }
            }
            $rot = strtoupper((string) ($r['clase_abc'] ?? 'C')) ?: 'C';
            if (! in_array($rot, ['A', 'B', 'C'], true)) {
                $this->err($i, 'clase_abc debe ser A, B o C');

                continue;
            }
            $this->save('PRODUCTS', 'codigo', (string) $r['codigo'], [
                'codigo' => (string) $r['codigo'], 'desc' => $r['descripcion'], 'um' => strtoupper((string) $r['um']),
                'cat' => $r['categoria'] ?? '—', 'sub' => $r['subcategoria'] ?? '—', 'rot' => $rot,
                'min' => $this->num($r['minimo'] ?? 0), 'max' => $this->num($r['maximo'] ?? 0),
                'vidaUtil' => $this->num($r['vida_util_dias'] ?? 0), 'precio' => $this->dec($r['precio'] ?? 0),
                'estado' => $this->estado($r['estado'] ?? '', ['ACTIVO', 'INACTIVO'], 'ACTIVO'),
                'fabrica' => $r['cod_fabrica'] ?? '—', 'peso' => $this->dec($r['peso_kg'] ?? 0), 'serializado' => false,
            ], $mode, $i);
        }
        if ($mode === 'replace') {
            $withStock = DB::table('cw_stock')->pluck('codigo')->all();
            DB::table('cw_products')->whereNotIn('codigo', array_map('strval', array_column($keys, 'codigo')))->whereNotIn('codigo', $withStock)->delete();
        }
    }

    private function importClientes(array $rows, string $mode): void
    {
        $keys = $this->uniqueRows($rows, 'codigo');
        foreach ($keys as $i => $r) {
            $this->save('CLIENTS', 'codigo', (string) $r['codigo'], [
                'codigo' => (string) $r['codigo'], 'nombre' => $r['razon_social'], 'nit' => (string) ($r['nit'] ?? ''),
                'tel' => (string) ($r['telefono'] ?? ''), 'dpto' => $r['departamento'] ?? '—', 'ciudad' => $r['ciudad'] ?? '—',
                'canal' => $r['canal'] ?? '—', 'estado' => $this->estado($r['estado'] ?? '', ['ACTIVO', 'INACTIVO'], 'ACTIVO'),
                'pedidos' => (int) DB::table('cw_clients')->where('codigo', (string) $r['codigo'])->value('pedidos'),
                'direccion' => $r['direccion'] ?? null, 'zona' => $r['zona'] ?? null,
            ], $mode, $i);
        }
        if ($mode === 'replace') {
            $open = DB::table('cw_pedidos')->pluck('cliente')->all();
            DB::table('cw_clients')->whereNotIn('codigo', array_map('strval', array_column($keys, 'codigo')))->whereNotIn('codigo', $open)->delete();
        }
    }

    private function importChoferes(array $rows, string $mode): void
    {
        $keys = $this->uniqueRows($rows, 'ci');
        foreach ($keys as $i => $r) {
            $this->save('DRIVERS', 'ci', (string) $r['ci'], [
                'ci' => (string) $r['ci'], 'nombre' => trim($r['nombre'].' '.($r['apellidos'] ?? '')),
                'lic' => $r['licencia'] ?? '—', 'tel' => (string) ($r['telefono'] ?? ''),
                'estado' => $this->estado($r['estado'] ?? '', ['ACTIVO', 'INACTIVO', 'EN RUTA'], 'ACTIVO'),
                'viajes' => (int) DB::table('cw_drivers')->where('ci', (string) $r['ci'])->value('viajes'),
            ], $mode, $i);
        }
        if ($mode === 'replace') {
            DB::table('cw_drivers')->whereNotIn('ci', array_map('strval', array_column($keys, 'ci')))->delete();
        }
    }

    private function importCamiones(array $rows, string $mode): void
    {
        $keys = $this->uniqueRows($rows, 'placa');
        foreach ($keys as $i => $r) {
            $cap = trim(($r['capacidad_kg'] ?? '') !== '' ? number_format((float) $r['capacidad_kg'], 0, ',', '.').' kg' : '');
            $cap .= ($r['volumen_m3'] ?? '') !== '' ? ($cap ? ' · ' : '').$r['volumen_m3'].' m³' : '';
            if (($r['chofer_ci'] ?? '') !== '' && ! DB::table('cw_drivers')->where('ci', (string) $r['chofer_ci'])->exists()) {
                $this->err($i, "chofer_ci {$r['chofer_ci']} no existe (importa primero los choferes)");

                continue;
            }
            $this->save('TRUCKS', 'placa', strtoupper((string) $r['placa']), [
                'placa' => strtoupper((string) $r['placa']), 'tipo' => $r['tipo'], 'marca' => trim(($r['marca'] ?? '').' '.($r['modelo'] ?? '')),
                'cap' => $cap ?: '—', 'estado' => $this->estado($r['estado'] ?? '', ['DISPONIBLE', 'EN RUTA', 'MANTENIMIENTO', 'INACTIVO'], 'DISPONIBLE'),
                'chofer' => ($r['chofer_ci'] ?? '') !== '' ? (string) $r['chofer_ci'] : null,
            ], $mode, $i);
        }
        if ($mode === 'replace') {
            DB::table('cw_trucks')->whereNotIn('placa', array_map(fn ($p) => strtoupper((string) $p), array_column($keys, 'placa')))->delete();
        }
    }

    private function importColectores(array $rows, string $mode): void
    {
        $keys = $this->uniqueRows($rows, 'codigo');
        foreach ($keys as $i => $r) {
            $wh = $this->whOf($r);
            $cur = DB::table('cw_devices')->where('code', (string) $r['codigo'])->first();
            $this->save('DEVICES', 'code', (string) $r['codigo'], [
                'id' => (string) $r['codigo'], 'modelo' => $r['modelo'], 'so' => $r['sistema'] ?? 'Android',
                'bat' => $cur->bat ?? 100, 'app' => $r['version_app'] ?? ($cur->app ?? '0.4.0'),
                'user' => ($r['usuario'] ?? '') !== '' ? strtolower((string) $r['usuario']) : null,
                'wh' => $wh, 'online' => (bool) ($cur->online ?? false), 'cola' => (int) ($cur->cola ?? 0),
            ], $mode, $i);
        }
        if ($mode === 'replace') {
            DB::table('cw_devices')->whereNotIn('code', array_map('strval', array_column($keys, 'codigo')))->delete();
        }
    }

    // ---------------------------------------------------------------- topología

    private function importRacks(array $rows, string $mode): void
    {
        $keys = $this->uniqueRows($rows, 'codigo');
        foreach ($keys as $i => $r) {
            $code = strtoupper((string) $r['codigo']);
            $wh = $this->whOf($r);
            $filas = $this->num($r['niveles']);
            $cols = $this->num($r['columnas']);
            if ($filas < 1 || $cols < 1 || $filas > 20 || $cols > 99) {
                $this->err($i, 'niveles (1–20) y columnas (1–99) deben ser números válidos');

                continue;
            }
            $other = DB::table('cw_racks')->where('code', $code)->value('wh');
            if ($other && $other !== $wh) {
                $this->err($i, "El rack $code ya existe en el almacén $other");

                continue;
            }
            $cap = $this->num($r['capacidad'] ?? 6) ?: 6;
            if (! $this->save('RACKS', 'code', $code, [
                'id' => $code, 'name' => $r['nombre'], 'filas' => $filas, 'cols' => $cols, 'zona' => $r['zona'] ?? 'Reserva',
                'wh' => $wh, 'tipo' => $r['tipo'] ?? 'Rack', 'cap' => $cap,
            ], $mode, $i, false)) {
                continue;
            }
            $this->ok++;
            $base = ((int) DB::table('cw_locations')->max('sort_seq')) + 5;
            for ($c = 1; $c <= $cols; $c++) {
                for ($f = 1; $f <= $filas; $f++) {
                    $loc = sprintf('%s-C%02d-N%d', $code, $c, $f);
                    if (DB::table('cw_locations')->where('code', $loc)->exists()) {
                        continue;
                    }
                    $seq = $base + ($c - 1) * $filas + ($c % 2 ? $f : $filas - $f + 1);
                    $this->insert('LOCATIONS', [
                        'id' => $loc, 'rack' => $code, 'fila' => $f, 'col' => $c, 'wh' => $wh, 'cap' => $cap,
                        'sort' => $seq, 'blocked' => false, 'tipo' => $r['tipo'] ?? 'Rack',
                    ]);
                }
            }
        }
        if ($mode === 'replace') {
            $keep = array_map(fn ($c) => strtoupper((string) $c), array_column($keys, 'codigo'));
            $gone = DB::table('cw_racks')->where('wh', $this->wh)->whereNotIn('code', $keep)->pluck('code');
            foreach ($gone as $rack) {
                if (! DB::table('cw_stock')->where('rack', $rack)->exists()) {
                    DB::table('cw_locations')->where('rack', $rack)->delete();
                    DB::table('cw_racks')->where('code', $rack)->delete();
                }
            }
        }
    }

    private function importUbicaciones(array $rows, string $mode): void
    {
        $keys = $this->uniqueRows($rows, 'codigo');
        foreach ($keys as $i => $r) {
            $rack = DB::table('cw_racks')->where('code', strtoupper((string) $r['rack']))->first();
            if (! $rack) {
                $this->err($i, "El rack {$r['rack']} no existe (impórtalo antes o créalo en el mapa)");

                continue;
            }
            $code = strtoupper((string) $r['codigo']);
            $this->save('LOCATIONS', 'code', $code, [
                'id' => $code, 'rack' => $rack->code, 'fila' => $this->num($r['nivel']), 'col' => $this->num($r['columna']),
                'wh' => $rack->wh, 'cap' => $this->num($r['capacidad'] ?? $rack->cap ?? 6) ?: 6,
                'sort' => ($r['sort_seq'] ?? '') !== '' ? $this->num($r['sort_seq']) : ((int) DB::table('cw_locations')->max('sort_seq') + 1),
                'blocked' => strtoupper((string) ($r['estado'] ?? '')) === 'BLOQUEADA', 'tipo' => $r['tipo'] ?? ($rack->tipo ?? 'Rack'),
            ], $mode, $i);
            if ($this->num($r['columna']) > (int) $rack->cols || $this->num($r['nivel']) > (int) $rack->filas) {
                DB::table('cw_racks')->where('id', $rack->id)->update([
                    'cols' => max((int) $rack->cols, $this->num($r['columna'])), 'filas' => max((int) $rack->filas, $this->num($r['nivel'])),
                ]);
            }
        }
        if ($mode === 'replace') {
            $used = DB::table('cw_stock')->pluck('loc')->all();
            DB::table('cw_locations')->where('wh', $this->wh)->whereNotIn('code', array_map(fn ($c) => strtoupper((string) $c), array_column($keys, 'codigo')))
                ->whereNotIn('code', $used)->delete();
        }
    }

    // ---------------------------------------------------------------- stock y documentos

    private function importStockInicial(array $rows, string $mode): void
    {
        if ($mode === 'replace') {
            DB::table('cw_stock')->where('wh', $this->wh)->delete();
        }
        $today = now('America/La_Paz')->toDateString();
        foreach ($rows as $i => $r) {
            $wh = $this->whOf($r);
            $loc = DB::table('cw_locations')->where('code', strtoupper((string) $r['ubicacion']))->first();
            if (! $loc) {
                $this->err($i, "La ubicación {$r['ubicacion']} no existe");

                continue;
            }
            if ($loc->wh !== $wh) {
                $this->err($i, "La ubicación {$loc->code} es del almacén {$loc->wh}, no de $wh");

                continue;
            }
            if (! DB::table('cw_products')->where('codigo', (string) $r['codigo'])->exists()) {
                $this->err($i, "El producto {$r['codigo']} no existe (importa primero los productos)");

                continue;
            }
            $qty = $this->num($r['cantidad']);
            if ($qty <= 0) {
                $this->err($i, 'cantidad debe ser mayor a cero');

                continue;
            }
            $lote = ($r['lote'] ?? '') !== '' ? (string) $r['lote'] : 'S/L';
            $q = DB::table('cw_stock')->where('wh', $wh)->where('loc', $loc->code)->where('codigo', (string) $r['codigo'])->where('lote', $lote);
            $cur = $q->first();
            if ($cur && $mode === 'insert') {
                $this->err($i, 'Ya existe saldo para esa ubicación, producto y lote');

                continue;
            }
            $delta = $qty - (int) ($cur->qty ?? 0);
            $row = [
                'loc' => $loc->code, 'rack' => $loc->rack, 'wh' => $wh, 'codigo' => (string) $r['codigo'], 'lote' => $lote, 'qty' => $qty,
                'ingreso' => $this->date($r['fecha_ingreso'] ?? '') ?? $today, 'venc' => $this->date($r['vencimiento'] ?? ''),
                'estado' => $this->estado($r['estado'] ?? '', ['DISPONIBLE', 'CUARENTENA', 'VENCIDO', 'BLOQUEADO'], 'DISPONIBLE'),
                'reservado' => min((int) ($cur->reservado ?? 0), $qty),
            ];
            if ($cur) {
                DB::table('cw_stock')->where('id', $cur->id)->update($row + ['updated_at' => now()]);
            } else {
                DB::table('cw_stock')->insert($row + ['position' => (int) DB::table('cw_stock')->max('position') + 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($delta !== 0) {
                $this->insert('KARDEX', [
                    'ts' => now('America/La_Paz')->format('Y-m-d H:i'), 'tipo' => 'Carga inicial', 'dir' => $delta > 0 ? 'in' : 'out',
                    'codigo' => (string) $r['codigo'], 'lote' => $lote, 'qty' => $delta, 'from' => 'IMPORTACIÓN', 'to' => $loc->code,
                    'user' => $this->username, 'doc' => 'CARGA-INICIAL', 'device' => 'Escritorio', 'offline' => false, 'wh' => $wh,
                ], true);
            }
            $this->ok++;
        }
    }

    private function importIngresos(array $rows, string $mode): void
    {
        $groups = [];
        foreach ($rows as $i => $r) {
            if (! DB::table('cw_products')->where('codigo', (string) $r['codigo'])->exists()) {
                $this->err($i, "El producto {$r['codigo']} no existe");

                continue;
            }
            if ($this->num($r['cantidad']) <= 0) {
                $this->err($i, 'cantidad debe ser mayor a cero');

                continue;
            }
            $groups[(string) $r['nro']][$i] = $r;
        }
        foreach ($groups as $nro => $lines) {
            $first = reset($lines);
            $i = array_key_first($lines);
            $wh = $this->whOf($first);
            $muelle = ($first['muelle'] ?? '') !== '' ? strtoupper((string) $first['muelle'])
                : DB::table('cw_locations')->join('cw_racks', 'cw_racks.code', '=', 'cw_locations.rack')->where('cw_locations.wh', $wh)->where('cw_racks.zona', 'Recepción')->orderBy('cw_locations.sort_seq')->value('cw_locations.code');
            if (! $muelle || ! DB::table('cw_locations')->where('code', $muelle)->exists()) {
                $this->err($i, "La orden $nro no tiene un muelle de recepción válido en $wh");

                continue;
            }
            $existing = DB::table('cw_ingresos')->where('nro', $nro)->first();
            if ($existing && ! in_array($existing->estado, ['Borrador', 'Habilitado'], true)) {
                $this->err($i, "La orden $nro ya está {$existing->estado}; no se modifica");

                continue;
            }
            $tipo = $this->pickOne($first['tipo'], ['Producción', 'Compra', 'Devolución', 'Transferencia'], 'Producción');
            $this->save('INGRESOS', 'nro', $nro, [
                'nro' => $nro, 'fecha' => $this->date($first['fecha'] ?? '') ?? now('America/La_Paz')->toDateString(), 'tipo' => $tipo,
                'origen' => $first['origen'] ?? '—', 'estado' => $this->pickOne($first['estado'] ?? '', ['Borrador', 'Habilitado'], 'Habilitado'),
                'lines' => array_values(array_map(fn ($l) => [
                    'codigo' => (string) $l['codigo'], 'qty' => $this->num($l['cantidad']), 'rec' => 0,
                    'lote' => ($l['lote'] ?? '') !== '' ? (string) $l['lote'] : 'S/L', 'turno' => $l['turno'] ?? CarmenImport::shift(),
                ], $lines)),
                'doc' => $first['documento'] ?? '—', 'usuario' => $this->username, 'muelle' => $muelle, 'wh' => $wh, 'obs' => null,
            ], $mode, $i);
        }
    }

    private function importPedidos(array $rows, string $mode): void
    {
        $groups = [];
        foreach ($rows as $i => $r) {
            if (! DB::table('cw_clients')->where('codigo', (string) $r['cliente'])->exists()) {
                $this->err($i, "El cliente {$r['cliente']} no existe (importa primero los clientes)");

                continue;
            }
            if (! DB::table('cw_products')->where('codigo', (string) $r['codigo'])->exists()) {
                $this->err($i, "El producto {$r['codigo']} no existe");

                continue;
            }
            if ($this->num($r['cantidad']) <= 0) {
                $this->err($i, 'cantidad debe ser mayor a cero');

                continue;
            }
            $groups[(string) $r['nro']][$i] = $r;
        }
        foreach ($groups as $nro => $lines) {
            $first = reset($lines);
            $i = array_key_first($lines);
            $existing = DB::table('cw_pedidos')->where('nro', $nro)->first();
            if ($existing && $existing->estado !== 'Recibido') {
                $this->err($i, "El pedido $nro ya está en {$existing->estado}; no se modifica");

                continue;
            }
            $this->save('PEDIDOS', 'nro', $nro, [
                'nro' => $nro, 'cliente' => (string) $first['cliente'], 'fecha' => $this->date($first['fecha'] ?? '') ?? now('America/La_Paz')->toDateString(),
                'estado' => 'Recibido', 'lines' => array_values(array_map(fn ($l) => ['codigo' => (string) $l['codigo'], 'qty' => $this->num($l['cantidad']), 'pick' => 0], $lines)),
                'picker' => null, 'prioridad' => $this->pickOne($first['prioridad'] ?? '', ['Normal', 'Urgente'], 'Normal'), 'transporte' => null,
                'bultos' => $this->num($first['bultos'] ?? 0) ?: 1, 'ola' => '—', 'ref' => $first['referencia'] ?? '—',
                'wh' => $this->whOf($first), 'obs' => $first['observacion'] ?? null,
            ], $mode, $i);
        }
    }

    // ---------------------------------------------------------------- usuarios

    private function importUsuariosEscritorio(array $rows, string $mode): void
    {
        $roles = ['Administrador', 'Encargado de almacén', 'Operador', 'Supervisor (lectura)'];
        foreach ($this->uniqueRows($rows, 'usuario') as $i => $r) {
            $username = strtolower((string) $r['usuario']);
            if (! preg_match('/^[a-z0-9._-]+$/', $username)) {
                $this->err($i, 'usuario solo admite letras, números, punto, guion y guion bajo');

                continue;
            }
            $rol = $this->pickOne($r['rol'], $roles, null) ?? (str_starts_with(strtolower($r['rol']), 'encargado') ? 'Encargado de almacén' : null);
            if (! $rol) {
                $this->err($i, 'rol debe ser: '.implode(', ', $roles));

                continue;
            }
            if ($mode === 'insert' && User::where('username', $username)->exists()) {
                $this->err($i, 'El usuario ya existe');

                continue;
            }
            $whs = array_values(array_filter(array_map('trim', preg_split('/[|,;]/', strtoupper((string) $r['almacenes'])))));
            $user = User::firstOrNew(['username' => $username]);
            $isNew = ! $user->exists;
            $user->name = $r['nombre'];
            $user->client_type = DB::table('cw_users_col')->where('username', $username)->exists() ? 'AMBOS' : 'ESCRITORIO';
            $user->is_active = $this->estado($r['estado'] ?? '', ['HABILITADO', 'DESHABILITADO'], 'HABILITADO') === 'HABILITADO';
            if (($r['contrasena'] ?? '') !== '' || $isNew) {
                $pass = ($r['contrasena'] ?? '') !== '' ? (string) $r['contrasena'] : Str::lower(Str::random(4)).random_int(1000, 9999);
                $user->password = $pass;
                $this->created[] = ['usuario' => $username, 'nombre' => $r['nombre'], 'contrasena' => $pass];
            }
            $user->save();
            $this->save('USERS_DESK', 'username', $username, [
                'user' => $username, 'nombre' => $r['nombre'], 'rol' => $rol, 'wh' => $whs ?: [$this->wh],
                'estado' => $user->is_active ? 'HABILITADO' : 'DESHABILITADO',
                'ult' => DB::table('cw_users_desk')->where('username', $username)->value('ult') ?? '—', 'correo' => $r['correo'] ?? null,
            ], 'upsert', $i);
        }
    }

    private function importUsuariosColector(array $rows, string $mode): void
    {
        foreach ($this->uniqueRows($rows, 'usuario') as $i => $r) {
            $username = strtolower((string) $r['usuario']);
            if (! preg_match('/^[a-z0-9._-]+$/', $username)) {
                $this->err($i, 'usuario solo admite letras, números, punto, guion y guion bajo');

                continue;
            }
            $rol = $this->pickOne($r['rol'], ['Encargado', 'Operador'], null);
            if (! $rol) {
                $this->err($i, 'rol debe ser Encargado u Operador');

                continue;
            }
            if (($r['pin'] ?? '') !== '' && ! preg_match('/^\d{4,6}$/', (string) $r['pin'])) {
                $this->err($i, 'pin debe tener 4 a 6 dígitos');

                continue;
            }
            if ($mode === 'insert' && User::where('username', $username)->exists()) {
                $this->err($i, 'El usuario ya existe');

                continue;
            }
            $user = User::firstOrNew(['username' => $username]);
            $isNew = ! $user->exists;
            $user->name = $r['nombre'];
            $user->client_type = DB::table('cw_users_desk')->where('username', $username)->exists() ? 'AMBOS' : 'COLECTOR';
            $user->is_active = $this->estado($r['estado'] ?? '', ['HABILITADO', 'DESHABILITADO'], 'HABILITADO') === 'HABILITADO';
            if ($isNew) {
                $user->password = Str::random(16);
            }
            $cred = null;
            if (($r['pin'] ?? '') !== '' || $isNew || ! $user->pin) {
                $pin = ($r['pin'] ?? '') !== '' ? (string) $r['pin'] : (string) random_int(1000, 9999);
                $user->pin = $pin;
                $cred = $pin;
            }
            $user->save();
            if ($cred) {
                $this->created[] = ['usuario' => $username, 'nombre' => $r['nombre'], 'pin' => $cred];
            }
            $device = ($r['equipo'] ?? '') !== '' ? (string) $r['equipo'] : null;
            $this->save('USERS_COL', 'username', $username, [
                'user' => $username, 'nombre' => $r['nombre'], 'rol' => $rol, 'wh' => strtoupper((string) $r['almacen']),
                'estado' => $user->is_active ? 'HABILITADO' : 'DESHABILITADO', 'device' => $device,
                'online' => (bool) DB::table('cw_users_col')->where('username', $username)->value('online'), 'documento' => $r['documento'] ?? null,
            ], 'upsert', $i);
            if ($device) {
                DB::table('cw_devices')->where('code', $device)->update(['username' => $username]);
            }
        }
    }

    // ---------------------------------------------------------------- utilidades

    public static function shift(): string
    {
        $h = (int) now('America/La_Paz')->format('G');

        return $h >= 6 && $h < 14 ? 'T1' : ($h >= 14 && $h < 22 ? 'T2' : 'T3');
    }

    /** Filas con clave única dentro del archivo (las repetidas se informan). */
    private function uniqueRows(array $rows, string $key): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $i => $r) {
            $k = strtoupper((string) $r[$key]);
            if (isset($seen[$k])) {
                $this->err($i, "$key repetido en el archivo (fila ".($seen[$k] + 2).')');

                continue;
            }
            $seen[$k] = $i;
            $out[$i] = $r;
        }

        return $out;
    }

    /** Guarda según el modo. Devuelve false si se omitió. */
    private function save(string $dataset, string $keyColumn, string $key, array $record, string $mode, int $i, bool $count = true): bool
    {
        $table = CarmenSchema::DATASETS[$dataset]['table'];
        $existing = DB::table($table)->where($keyColumn, $key)->first();
        if ($existing && $mode === 'insert') {
            $this->err($i, "Ya existe $key (modo «solo nuevos»)");

            return false;
        }
        $row = CarmenData::toRow($dataset, $record, $existing->position ?? ((int) DB::table($table)->max('position') + 1));
        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($row + ['updated_at' => now()]);
        } else {
            DB::table($table)->insert($row + ['created_at' => now(), 'updated_at' => now()]);
        }
        if ($count) {
            $this->ok++;
        }

        return true;
    }

    private function insert(string $dataset, array $record, bool $first = false): void
    {
        $table = CarmenSchema::DATASETS[$dataset]['table'];
        $pos = $first ? (int) DB::table($table)->min('position') - 1 : (int) DB::table($table)->max('position') + 1;
        DB::table($table)->insert(CarmenData::toRow($dataset, $record, $pos) + ['created_at' => now(), 'updated_at' => now()]);
    }

    private function err(int $i, string $msg): void
    {
        $this->errors[] = ['row' => $i + 2, 'msg' => $msg];
    }

    private function whOf(array $r): string
    {
        $wh = strtoupper((string) ($r['almacen'] ?? ''));

        return $wh !== '' && DB::table('cw_warehouses')->where('code', $wh)->exists() ? $wh : $this->wh;
    }

    private function num(mixed $v): int
    {
        return (int) round((float) str_replace(',', '.', (string) $v));
    }

    private function dec(mixed $v): float
    {
        return (float) str_replace(',', '.', (string) $v);
    }

    private function estado(string $v, array $allowed, string $default): string
    {
        $v = strtoupper(trim($v));

        return in_array($v, $allowed, true) ? $v : $default;
    }

    private function pickOne(?string $v, array $allowed, ?string $default): ?string
    {
        $n = fn ($s) => strtolower(Str::ascii((string) $s));
        foreach ($allowed as $a) {
            if ($n($a) === $n($v)) {
                return $a;
            }
        }

        return $default;
    }

    /** Acepta AAAA-MM-DD, DD/MM/AAAA o número de serie de Excel. */
    private function date(mixed $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
            return now()->setDate(1899, 12, 30)->addDays((int) $v)->toDateString();
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $v, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $v, $m)) {
            return "$m[1]-$m[2]-$m[3]";
        }

        return null;
    }
}
