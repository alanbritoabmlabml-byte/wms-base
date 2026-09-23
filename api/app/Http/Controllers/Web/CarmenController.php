<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CarmenData;
use App\Support\CarmenImport;
use App\Support\CarmenSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Carmen WMS — escritorio y colector.
 *
 * Laravel entrega la página, autentica (contraseña en escritorio, PIN en el
 * colector), sirve los datos desde las tablas cw_* y aplica los cambios:
 * registros de documentos y maestros, movimientos de stock (con kardex
 * inmutable), usuarios con credenciales e importaciones masivas.
 */
class CarmenController extends Controller
{
    /** Permisos (mismo orden que la matriz de roles de la interfaz). */
    public const PERMS = ['recibir', 'picking', 'reubicar', 'ajustar', 'aprobar', 'reportes', 'maestros', 'config'];

    public const DEFAULT_PERMS = [
        'Administrador' => [1, 1, 1, 1, 1, 1, 1, 1],
        'Encargado de almacén' => [1, 1, 1, 1, 1, 1, 1, 0],
        'Encargado' => [1, 1, 1, 1, 1, 1, 0, 0],
        'Operador' => [1, 1, 1, 0, 0, 0, 0, 0],
        'Supervisor (lectura)' => [0, 0, 0, 0, 0, 1, 0, 0],
    ];

    /** Dataset => permisos que permiten escribirlo (cualquiera de ellos). */
    private const WRITE_PERMS = [
        'INGRESOS' => ['recibir', 'maestros'],
        'PEDIDOS' => ['picking', 'maestros'],
        'DESPACHOS' => ['picking'],
        'CONTEOS' => ['recibir', 'picking', 'reubicar', 'ajustar', 'aprobar'],
        'AJUSTES' => ['ajustar', 'aprobar'],
        'PRODUCTS' => ['maestros'],
        'CLIENTS' => ['maestros'],
        'DRIVERS' => ['maestros'],
        'TRUCKS' => ['maestros'],
        'RACKS' => ['maestros'],
        'LOCATIONS' => ['maestros', 'aprobar'],
        'DEVICES' => ['maestros', 'config'],
        'LABELS' => ['maestros', 'config'],
        'SETTINGS' => ['config'],
        'IMPORT_HISTORY' => ['maestros'],
        'WAREHOUSES' => ['config'],
    ];

    /** Datasets que se pueden eliminar desde la interfaz. */
    private const DELETABLE = ['PRODUCTS', 'CLIENTS', 'DRIVERS', 'TRUCKS', 'RACKS', 'LOCATIONS', 'DEVICES', 'INGRESOS', 'PEDIDOS'];

    public function app(Request $request): View
    {
        $ready = Schema::hasTable('cw_warehouses');

        $warehouses = $ready
            ? DB::table('cw_warehouses')->where('wh_type', '!=', 'TR')->orderBy('position')->get(['code', 'name'])
                ->map(fn ($w) => ['id' => $w->code, 'name' => $w->name])->all()
            : [];

        $ira = $ready ? DB::table('cw_conteos')->where('estado', 'Finalizado')->orderByDesc('nro')->value('ira') : null;

        return view('carmen.app', [
            'warehouses' => $warehouses,
            'stats' => [
                'warehouses' => count($warehouses),
                'ira' => $ira !== null ? number_format($ira * 100, 1, ',', '.').' %' : '—',
                'online' => $ready ? DB::table('cw_devices')->where('online', true)->count() : 0,
            ],
            'version' => substr(md5(implode('|', array_map(fn ($f) => (string) @filemtime(public_path('carmen/'.$f)), ['core.js', 'views-ops.js', 'views-stock.js', 'views-master.js', 'views-config.js', 'collector.js', 'boot.js', 'app.css']))), 0, 8),
        ]);
    }

    /** Script con CW (sesión) y las constantes de datos (WAREHOUSES, STOCK, PEDIDOS…). */
    public function data(Request $request): Response
    {
        $datasets = $request->user() ? $this->datasets() : [];

        return response(CarmenData::script($datasets, $this->session($request)), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Los mismos datos en JSON, para refrescar sin recargar la página. */
    public function dataJson(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->datasets(), 'wh' => $request->session()->get('cw.wh')]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string', 'max:120'],
            'wh' => ['nullable', 'string', 'max:20'],
            'mode' => ['nullable', 'in:desk,col'],
        ], [], ['username' => 'usuario', 'password' => 'contraseña o PIN']);

        $mode = $data['mode'] ?? 'desk';
        $user = User::where('username', $data['username'])->first();
        $fail = fn () => throw ValidationException::withMessages(['username' => $mode === 'col'
            ? 'Usuario o PIN incorrectos, o usuario deshabilitado.'
            : 'Usuario o contraseña incorrectos, o usuario deshabilitado.']);

        if (! $user || ! $user->is_active) {
            $fail();
        }

        $type = $user->client_type ?: 'AMBOS';
        if ($mode === 'col') {
            $okPin = $user->pin && Hash::check($data['password'], $user->pin);
            $okPass = Hash::check($data['password'], $user->password);
            if (! $okPin && ! $okPass) {
                $fail();
            }
            if ($type === 'ESCRITORIO' && ! DB::table('cw_users_col')->where('username', $user->username)->exists()) {
                // Un usuario de escritorio puede usar el colector con su contraseña.
                if (! $okPass) {
                    $fail();
                }
            }
        } else {
            if (! Hash::check($data['password'], $user->password)) {
                $fail();
            }
            if ($type === 'COLECTOR') {
                throw ValidationException::withMessages(['username' => 'Este usuario solo tiene acceso desde el colector. Elige «Colector (PIN)».']);
            }
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('cw.wh', $this->allowedWarehouse($user->username, $data['wh'] ?? null));
        $request->session()->put('cw.mode', $mode);
        $user->forceFill(['last_login_at' => now()])->save();
        $this->touchProfile($user->username, $mode);

        return response()->json(['ok' => true, 'mode' => $mode]);
    }

    public function logout(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($request->user()) {
            DB::table('cw_users_col')->where('username', $request->user()->username)->update(['online' => false]);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson() ? response()->json(['ok' => true]) : redirect()->route('home');
    }

    public function switchWarehouse(Request $request): JsonResponse
    {
        $wh = $this->allowedWarehouse($request->user()->username, (string) $request->input('wh'));
        $request->session()->put('cw.wh', $wh);

        return response()->json(['ok' => true, 'wh' => $wh]);
    }

    /** Crea o actualiza un registro por su clave natural. */
    public function upsert(Request $request, string $dataset, string $key): JsonResponse
    {
        $this->authorizeDataset($request, $dataset);
        $def = CarmenSchema::DATASETS[$dataset];
        $keyColumn = CarmenData::keyColumn($dataset);
        abort_if($keyColumn === null, 422, 'Este conjunto no tiene clave.');

        $record = $request->json()->all();
        $record[$def['key']] = $dataset === 'CONTEOS' ? (int) $key : $key;

        // Reglas que no dependen de la interfaz.
        if ($dataset === 'AJUSTES' && in_array($record['estado'] ?? '', ['Aprobado', 'Rechazado'], true)) {
            $this->requirePerm($request, ['aprobar']);
        }
        if ($dataset === 'CONTEOS' && ($record['estado'] ?? '') === 'Finalizado') {
            $this->requirePerm($request, ['aprobar']);
        }
        if ($dataset === 'SETTINGS' && $key === 'perms') {
            $this->requirePerm($request, ['config']);
        }

        $existing = DB::table($def['table'])->where($keyColumn, $key)->first();
        $position = $existing->position ?? ((int) DB::table($def['table'])->min('position') - 1);
        $row = CarmenData::toRow($dataset, $record, $position);

        if ($existing) {
            DB::table($def['table'])->where('id', $existing->id)->update($row + ['updated_at' => now()]);
        } else {
            DB::table($def['table'])->insert($row + ['created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['ok' => true, 'created' => ! $existing]);
    }

    /** Elimina un registro (maestros sin uso, borradores). */
    public function destroy(Request $request, string $dataset, string $key): JsonResponse
    {
        abort_unless(in_array($dataset, self::DELETABLE, true), 404);
        $this->authorizeDataset($request, $dataset);
        $def = CarmenSchema::DATASETS[$dataset];
        $col = CarmenData::keyColumn($dataset);

        $inUse = match ($dataset) {
            'PRODUCTS' => DB::table('cw_stock')->where('codigo', $key)->where('qty', '>', 0)->exists() ? 'El producto tiene stock.' : null,
            'LOCATIONS' => DB::table('cw_stock')->where('loc', $key)->exists() ? 'La ubicación tiene stock.' : null,
            'RACKS' => DB::table('cw_stock')->where('rack', $key)->exists() ? 'El rack tiene stock.' : null,
            'CLIENTS' => DB::table('cw_pedidos')->where('cliente', $key)->where('estado', '!=', 'Despachado')->exists() ? 'El cliente tiene pedidos abiertos.' : null,
            'INGRESOS' => DB::table('cw_ingresos')->where('nro', $key)->whereNotIn('estado', ['Borrador', 'Anulado'])->exists() ? 'Solo se eliminan órdenes en borrador o anuladas.' : null,
            'PEDIDOS' => DB::table('cw_pedidos')->where('nro', $key)->where('estado', '!=', 'Recibido')->exists() ? 'Solo se eliminan pedidos sin preparar.' : null,
            default => null,
        };
        if ($inUse) {
            return response()->json(['message' => $inUse], 422);
        }

        if ($dataset === 'RACKS') {
            DB::table('cw_locations')->where('rack', $key)->delete();
        }
        DB::table($def['table'])->where($col, $key)->delete();

        return response()->json(['ok' => true]);
    }

    /** Agrega un registro sin clave (historial de importaciones). */
    public function append(Request $request, string $dataset): JsonResponse
    {
        abort_unless($dataset === 'IMPORT_HISTORY', 404);
        $table = CarmenSchema::DATASETS[$dataset]['table'];
        $position = (int) DB::table($table)->min('position') - 1;
        DB::table($table)->insert(CarmenData::toRow($dataset, $request->json()->all(), $position) + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true], 201);
    }

    /**
     * Movimientos de stock. Cada movimiento descuenta del origen (si es una
     * ubicación real), suma en el destino y deja una línea en el kardex.
     * Todo o nada: si falta stock en un origen no se aplica ninguno.
     */
    public function movements(Request $request): JsonResponse
    {
        $this->requirePerm($request, ['recibir', 'picking', 'reubicar', 'ajustar', 'aprobar']);
        $request->validate([
            'wh' => ['required', 'string', 'max:40'],
            'movs' => ['required', 'array', 'min:1', 'max:500'],
            'movs.*.tipo' => ['required', 'string', 'max:40'],
            'movs.*.codigo' => ['required', 'string', 'max:60'],
            'movs.*.qty' => ['required', 'integer'],
        ]);
        // validate() solo devuelve las claves con regla: se leen los movimientos completos.
        $data = ['wh' => (string) $request->input('wh'), 'movs' => array_values((array) $request->input('movs'))];

        $user = $request->user()->username;
        $locs = DB::table('cw_locations')->pluck('rack', 'code')->all();
        $created = [];

        DB::transaction(function () use ($data, $user, $locs, &$created) {
            $pos = (int) DB::table('cw_kardex')->min('position');
            foreach ($data['movs'] as $m) {
                $wh = $m['wh'] ?? $data['wh'];
                $qty = abs((int) $m['qty']);
                $from = $m['from'] ?? null;
                $to = $m['to'] ?? null;
                $lote = ($m['lote'] ?? '') !== '' ? $m['lote'] : null;
                $dir = $m['dir'] ?? 'mv';

                // Solo reserva / liberación de stock (no mueve ni deja kardex).
                if (($m['tipo'] ?? '') === 'Reserva') {
                    $row = $this->stockRow($wh, $m['loc'] ?? $from, $m['codigo'], $lote);
                    if ($row) {
                        $res = max(0, min((int) $row->qty, (int) $row->reservado + (int) $m['qty']));
                        DB::table('cw_stock')->where('id', $row->id)->update(['reservado' => $res, 'updated_at' => now()]);
                    }

                    continue;
                }

                if (($m['tipo'] ?? '') === 'Cambio de estado') {
                    $row = $this->stockRow($wh, $from, $m['codigo'], $lote);
                    if (! $row) {
                        throw ValidationException::withMessages(['movs' => "No hay saldo de {$m['codigo']} en $from."]);
                    }
                    DB::table('cw_stock')->where('id', $row->id)->update(['estado' => $m['estado'] ?? 'DISPONIBLE', 'updated_at' => now()]);
                    $qty = 0;
                    $to = null;
                    $from = null;
                    $lote = $row->lote;
                    $m['doc'] = ($m['doc'] ?? '—').' → '.($m['estado'] ?? 'DISPONIBLE');
                    $m['from'] = $row->loc;
                }

                if (in_array($dir, ['in', 'mv'], true) && ($m['tipo'] ?? '') !== 'Cambio de estado' && ! isset($locs[$to ?? ''])) {
                    throw ValidationException::withMessages(['movs' => "La ubicación de destino «{$to}» no existe."]);
                }
                if (in_array($dir, ['out', 'mv'], true) && ($m['tipo'] ?? '') !== 'Cambio de estado' && ! isset($locs[$from ?? ''])) {
                    throw ValidationException::withMessages(['movs' => "La ubicación de origen «{$from}» no existe."]);
                }

                if ($from && isset($locs[$from]) && $dir !== 'in-only') {
                    $row = $this->stockRow($wh, $from, $m['codigo'], $lote);
                    if (! $row || $row->qty < $qty) {
                        throw ValidationException::withMessages(['movs' => sprintf(
                            'Stock insuficiente de %s%s en %s: hay %s, se pidió %s.',
                            $m['codigo'], $lote ? " lote $lote" : '', $from, $row->qty ?? 0, $qty,
                        )]);
                    }
                    $lote = $row->lote;
                    $newQty = $row->qty - $qty;
                    $newRes = max(0, (int) $row->reservado - (int) ($m['unreserve'] ?? 0));
                    $newRes = min($newRes, $newQty);
                    if ($newQty <= 0) {
                        DB::table('cw_stock')->where('id', $row->id)->delete();
                    } else {
                        DB::table('cw_stock')->where('id', $row->id)->update(['qty' => $newQty, 'reservado' => $newRes, 'updated_at' => now()]);
                    }
                    $m['ingreso'] = $m['ingreso'] ?? $row->ingreso;
                    $m['venc'] = $m['venc'] ?? $row->venc;
                }

                if ($to && isset($locs[$to])) {
                    $row = DB::table('cw_stock')->where('wh', $wh)->where('loc', $to)->where('codigo', $m['codigo'])
                        ->where(fn ($q) => $lote === null ? $q->whereNull('lote') : $q->where('lote', $lote))->first();
                    if ($row) {
                        DB::table('cw_stock')->where('id', $row->id)->update(['qty' => $row->qty + $qty, 'updated_at' => now()]);
                    } else {
                        DB::table('cw_stock')->insert([
                            'position' => (int) DB::table('cw_stock')->max('position') + 1,
                            'loc' => $to, 'rack' => $locs[$to], 'wh' => $wh, 'codigo' => $m['codigo'], 'lote' => $lote,
                            'qty' => $qty, 'ingreso' => $m['ingreso'] ?? now('America/La_Paz')->toDateString(),
                            'venc' => $m['venc'] ?? null, 'estado' => $m['estado'] ?? 'DISPONIBLE', 'reservado' => 0,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }

                $k = [
                    'ts' => $m['ts'] ?? now('America/La_Paz')->format('Y-m-d H:i'),
                    'tipo' => $m['tipo'], 'dir' => in_array($dir, ['in', 'out', 'mv'], true) ? $dir : 'mv',
                    'codigo' => $m['codigo'], 'lote' => $lote ?? '—',
                    'qty' => $dir === 'out' ? -$qty : $qty,
                    'from' => $from ?? ($m['from'] ?? '—'), 'to' => $to ?? ($m['from'] ?? '—'),
                    'user' => $user, 'doc' => $m['doc'] ?? '—', 'device' => $m['device'] ?? 'Escritorio',
                    'offline' => (bool) ($m['offline'] ?? false), 'wh' => $wh,
                ];
                DB::table('cw_kardex')->insert(CarmenData::toRow('KARDEX', $k, --$pos) + ['created_at' => now(), 'updated_at' => now()]);
                $created[] = $k;
            }
        });

        return response()->json([
            'ok' => true,
            'kardex' => $created,
            'stock' => $this->rows('STOCK'),
        ]);
    }

    /** Guarda varios registros de un conjunto en una sola petición (racks con sus ubicaciones, ABC…). */
    public function bulk(Request $request, string $dataset): JsonResponse
    {
        $this->authorizeDataset($request, $dataset);
        $def = CarmenSchema::DATASETS[$dataset];
        $keyColumn = CarmenData::keyColumn($dataset);
        abort_if($keyColumn === null, 422);
        $records = $request->validate(['records' => ['required', 'array', 'max:2000']])['records'];
        DB::transaction(function () use ($records, $def, $keyColumn, $dataset) {
            $max = (int) DB::table($def['table'])->max('position');
            foreach ($records as $record) {
                $key = (string) ($record[$def['key']] ?? '');
                if ($key === '') {
                    continue;
                }
                $existing = DB::table($def['table'])->where($keyColumn, $key)->first();
                $row = CarmenData::toRow($dataset, $record, $existing->position ?? ++$max);
                if ($existing) {
                    DB::table($def['table'])->where('id', $existing->id)->update($row + ['updated_at' => now()]);
                } else {
                    DB::table($def['table'])->insert($row + ['created_at' => now(), 'updated_at' => now()]);
                }
            }
        });

        return response()->json(['ok' => true, 'count' => count($records)]);
    }

    /** Estado que reporta cada colector enrolado (batería, cola sin enviar, versión). */
    public function telemetry(Request $request): JsonResponse
    {
        $d = $request->validate(['id' => ['required', 'string', 'max:60'], 'bat' => ['nullable', 'integer', 'between:0,100'], 'cola' => ['nullable', 'integer', 'min:0'], 'app' => ['nullable', 'string', 'max:20'], 'wh' => ['nullable', 'string', 'max:40']]);
        $row = DB::table('cw_devices')->where('code', $d['id'])->first();
        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Equipo no registrado.'], 404);
        }
        DB::table('cw_devices')->where('id', $row->id)->update(array_filter([
            'bat' => $d['bat'] ?? null, 'cola' => $d['cola'] ?? 0, 'app' => $d['app'] ?? null, 'wh' => $d['wh'] ?? null,
            'online' => true, 'username' => $request->user()->username, 'updated_at' => now(),
        ], fn ($v) => $v !== null));
        DB::table('cw_users_col')->where('username', $request->user()->username)->update(['online' => true, 'device' => $d['id']]);

        return response()->json(['ok' => true]);
    }

    /** Crea o actualiza un usuario (escritorio, colector o ambos) con su acceso. */
    public function saveUser(Request $request): JsonResponse
    {
        $this->requirePerm($request, ['config']);
        $d = $request->validate([
            'user' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9._-]+$/i'],
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'in:ESCRITORIO,COLECTOR,AMBOS'],
            'rol' => ['nullable', 'string', 'max:60'],
            'colRol' => ['nullable', 'string', 'max:60'],
            'wh' => ['nullable', 'array'],
            'colWh' => ['nullable', 'string', 'max:40'],
            'correo' => ['nullable', 'string', 'max:160'],
            'documento' => ['nullable', 'string', 'max:60'],
            'device' => ['nullable', 'string', 'max:60'],
            'estado' => ['nullable', 'in:HABILITADO,DESHABILITADO'],
            'password' => ['nullable', 'string', 'min:6', 'max:120'],
            'pin' => ['nullable', 'digits_between:4,6'],
            'isNew' => ['nullable', 'boolean'],
        ], [], ['user' => 'usuario', 'nombre' => 'nombre']);

        $username = strtolower($d['user']);
        $user = User::firstOrNew(['username' => $username]);
        if (($d['isNew'] ?? false) && $user->exists) {
            throw ValidationException::withMessages(['user' => "El usuario «{$username}» ya existe."]);
        }
        if ($user->exists && $user->id === $request->user()->id && ($d['estado'] ?? 'HABILITADO') === 'DESHABILITADO') {
            throw ValidationException::withMessages(['estado' => 'No puedes deshabilitar tu propio usuario.']);
        }

        $out = [];
        $desk = in_array($d['tipo'], ['ESCRITORIO', 'AMBOS'], true);
        $col = in_array($d['tipo'], ['COLECTOR', 'AMBOS'], true);

        DB::transaction(function () use ($d, $user, $username, $desk, $col, &$out) {
            $user->name = $d['nombre'];
            $user->client_type = $d['tipo'];
            $user->is_active = ($d['estado'] ?? 'HABILITADO') === 'HABILITADO';
            if (! empty($d['correo'])) {
                $user->email = User::where('email', $d['correo'])->where('username', '!=', $username)->exists() ? $user->email : $d['correo'];
            }
            if (! $user->exists || ! empty($d['password'])) {
                $pass = $d['password'] ?? Str::lower(Str::random(4)).random_int(1000, 9999);
                $user->password = $pass;
                $out['password'] = $pass;
            }
            if ($col && (! $user->pin || ! empty($d['pin']))) {
                $pin = $d['pin'] ?? (string) random_int(1000, 9999);
                $user->pin = $pin;
                $out['pin'] = $pin;
            }
            $user->save();

            if ($desk) {
                $this->putRow('USERS_DESK', 'username', $username, [
                    'user' => $username, 'nombre' => $d['nombre'], 'rol' => $d['rol'] ?? 'Operador',
                    'wh' => array_values($d['wh'] ?? ['BOL2']), 'estado' => $d['estado'] ?? 'HABILITADO',
                    'ult' => DB::table('cw_users_desk')->where('username', $username)->value('ult') ?? '—',
                    'correo' => $d['correo'] ?? null,
                ]);
            } else {
                DB::table('cw_users_desk')->where('username', $username)->delete();
            }
            if ($col) {
                $this->putRow('USERS_COL', 'username', $username, [
                    'user' => $username, 'nombre' => $d['nombre'], 'rol' => $d['colRol'] ?? 'Operador',
                    'wh' => $d['colWh'] ?? (($d['wh'] ?? ['BOL2'])[0] ?? 'BOL2'), 'estado' => $d['estado'] ?? 'HABILITADO',
                    'device' => ($d['device'] ?? null) ?: null, 'online' => (bool) DB::table('cw_users_col')->where('username', $username)->value('online'),
                    'documento' => $d['documento'] ?? null,
                ]);
                if (! empty($d['device'])) {
                    DB::table('cw_devices')->where('username', $username)->where('code', '!=', $d['device'])->update(['username' => null]);
                    DB::table('cw_devices')->where('code', $d['device'])->update(['username' => $username]);
                }
            } else {
                DB::table('cw_users_col')->where('username', $username)->delete();
            }
        });

        return response()->json(['ok' => true] + $out + [
            'USERS_DESK' => $this->rows('USERS_DESK'),
            'USERS_COL' => $this->rows('USERS_COL'),
            'DEVICES' => $this->rows('DEVICES'),
        ]);
    }

    /** Genera una contraseña y/o PIN nuevos para un usuario. */
    public function resetUser(Request $request, string $username): JsonResponse
    {
        $this->requirePerm($request, ['config']);
        $user = User::where('username', $username)->firstOrFail();
        $out = [];
        if ($request->input('what', 'password') === 'pin') {
            $out['pin'] = (string) random_int(1000, 9999);
            $user->pin = $out['pin'];
        } else {
            $out['password'] = Str::lower(Str::random(4)).random_int(1000, 9999);
            $user->password = $out['password'];
        }
        $user->save();

        return response()->json(['ok' => true] + $out);
    }

    /** Cambio de contraseña propio. */
    public function changePassword(Request $request): JsonResponse
    {
        $d = $request->validate(['current' => ['required', 'string'], 'password' => ['required', 'string', 'min:6', 'max:120']], [], ['current' => 'contraseña actual', 'password' => 'contraseña nueva']);
        $user = $request->user();
        if (! Hash::check($d['current'], $user->password)) {
            throw ValidationException::withMessages(['current' => 'La contraseña actual no es correcta.']);
        }
        $user->password = $d['password'];
        $user->save();

        return response()->json(['ok' => true]);
    }

    /** Importación masiva desde Excel/CSV (filas ya mapeadas a los campos del WMS). */
    public function import(Request $request, string $dataset): JsonResponse
    {
        $this->requirePerm($request, ['maestros', 'config']);
        abort_unless(isset(CarmenImport::DATASETS[$dataset]), 404);
        $d = $request->validate([
            'rows' => ['required', 'array', 'max:20000'],
            'mode' => ['required', 'in:upsert,insert,replace'],
            'wh' => ['required', 'string', 'max:40'],
            'file' => ['nullable', 'string', 'max:200'],
        ]);

        $result = (new CarmenImport($request->user()->username, $d['wh']))->run($dataset, $d['rows'], $d['mode']);

        $label = CarmenImport::DATASETS[$dataset]['label'];
        $history = [
            'fecha' => now('America/La_Paz')->format('d/m/Y H:i'), 'dataset' => $label, 'archivo' => $d['file'] ?? 'archivo',
            'filas' => count($d['rows']), 'ok' => $result['ok'], 'err' => count($result['errors']), 'user' => $request->user()->username,
        ];
        DB::table('cw_import_history')->insert(CarmenData::toRow('IMPORT_HISTORY', $history, (int) DB::table('cw_import_history')->min('position') - 1) + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json($result + ['data' => $this->datasets()]);
    }

    // -----------------------------------------------------------------

    private function datasets(): array
    {
        $out = [];
        if (Schema::hasTable('cw_warehouses')) {
            foreach (array_keys(CarmenSchema::DATASETS) as $name) {
                $out[$name] = $this->rows($name);
            }
        }

        return $out;
    }

    private function rows(string $name): array
    {
        $def = CarmenSchema::DATASETS[$name];
        if (! Schema::hasTable($def['table'])) {
            return [];
        }

        return DB::table($def['table'])->orderBy('position')->orderBy('id')->get()
            ->map(fn ($row) => CarmenData::fromRow($name, $row))->all();
    }

    private function putRow(string $dataset, string $keyColumn, string $key, array $record): void
    {
        $table = CarmenSchema::DATASETS[$dataset]['table'];
        $existing = DB::table($table)->where($keyColumn, $key)->first();
        $row = CarmenData::toRow($dataset, $record, $existing->position ?? ((int) DB::table($table)->max('position') + 1));
        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($row + ['updated_at' => now()]);
        } else {
            DB::table($table)->insert($row + ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function stockRow(string $wh, ?string $loc, string $codigo, ?string $lote): ?object
    {
        if (! $loc) {
            return null;
        }
        $q = DB::table('cw_stock')->where('wh', $wh)->where('loc', $loc)->where('codigo', $codigo);
        if ($lote !== null) {
            $q->where('lote', $lote);
        }

        return $q->orderBy('ingreso')->orderByDesc('qty')->first();
    }

    private function authorizeDataset(Request $request, string $dataset): void
    {
        abort_unless(isset(self::WRITE_PERMS[$dataset], CarmenSchema::DATASETS[$dataset]), 404);
        $this->requirePerm($request, self::WRITE_PERMS[$dataset]);
    }

    /** Permisos efectivos del usuario (matriz de Parámetros → Roles). */
    public static function permsFor(string $username): array
    {
        $rol = DB::table('cw_users_desk')->where('username', $username)->value('rol')
            ?? DB::table('cw_users_col')->where('username', $username)->value('rol')
            ?? 'Operador';
        if ($rol === 'Administrador') {
            return self::PERMS;
        }
        $matrix = self::DEFAULT_PERMS;
        $saved = Schema::hasTable('cw_settings') ? DB::table('cw_settings')->where('k', 'perms')->value('v') : null;
        if ($saved) {
            $matrix = array_merge($matrix, json_decode($saved, true) ?: []);
        }
        $flags = $matrix[$rol] ?? $matrix['Operador'];
        // Un encargado de colector también puede recibir, preparar y aprobar en piso.
        if ($rol !== 'Encargado de almacén' && DB::table('cw_users_col')->where('username', $username)->value('rol') === 'Encargado') {
            $flags = array_map(fn ($a, $b) => $a || $b, $flags, $matrix['Encargado']);
        }

        return array_values(array_filter(self::PERMS, fn ($p, $i) => ! empty($flags[$i]), ARRAY_FILTER_USE_BOTH));
    }

    private function requirePerm(Request $request, array $any): void
    {
        $perms = self::permsFor($request->user()->username);
        if (! array_intersect($any, $perms)) {
            abort(response()->json(['message' => 'Tu rol no tiene permiso para esta acción.'], 403));
        }
    }

    private function touchProfile(string $username, string $mode): void
    {
        $ult = 'hoy '.now('America/La_Paz')->format('H:i');
        DB::table('cw_users_desk')->where('username', $username)->update(['ult' => $ult]);
        if ($mode === 'col') {
            DB::table('cw_users_col')->where('username', $username)->update(['online' => true]);
        }
    }

    /** Almacén pedido si el usuario lo tiene asignado; si no, el primero que tenga. */
    private function allowedWarehouse(string $username, ?string $wanted): string
    {
        $profile = Schema::hasTable('cw_users_desk') ? DB::table('cw_users_desk')->where('username', $username)->first() : null;
        $allowed = $profile ? (json_decode($profile->wh, true) ?: []) : [];
        if (! $allowed && Schema::hasTable('cw_users_col')) {
            $colWh = DB::table('cw_users_col')->where('username', $username)->value('wh');
            $allowed = $colWh ? [$colWh] : [];
        }
        if (! $allowed && Schema::hasTable('cw_warehouses')) {
            $allowed = DB::table('cw_warehouses')->where('wh_type', '!=', 'TR')->orderBy('position')->pluck('code')->all();
        }

        return in_array($wanted, $allowed, true) ? $wanted : ($allowed[0] ?? 'BOL2');
    }

    private function session(Request $request): array
    {
        $user = $request->user();
        $now = Carbon::now('America/La_Paz')->locale('es');
        $hour = (int) $now->format('G');

        $profile = $user ? DB::table('cw_users_desk')->where('username', $user->username)->first() : null;
        $colProfile = $user ? DB::table('cw_users_col')->where('username', $user->username)->first() : null;
        $device = $user ? DB::table('cw_devices')->where('username', $user->username)->value('code') : null;
        $nombre = $profile->nombre ?? $colProfile->nombre ?? $user?->name ?? '';

        return [
            'csrf' => csrf_token(),
            'user' => $user ? [
                'user' => $user->username,
                'nombre' => $nombre,
                'first' => explode(' ', trim($nombre))[0],
                'rol' => $profile->rol ?? ($colProfile ? $colProfile->rol.' (colector)' : 'Usuario'),
                'wh' => $profile ? (json_decode($profile->wh, true) ?: []) : ($colProfile ? [$colProfile->wh] : []),
                'type' => $user->client_type ?: 'AMBOS',
                'perms' => self::permsFor($user->username),
                'since' => $user->last_login_at ? 'hoy '.$user->last_login_at->setTimezone('America/La_Paz')->format('H:i') : 'hoy',
            ] : null,
            'wh' => $request->session()->get('cw.wh'),
            'mode' => $request->session()->get('cw.mode', 'desk'),
            'device' => $device ?? 'Escritorio',
            'now' => $now->format('Y-m-d H:i'),
            'today' => $now->isoFormat('dddd D [de] MMMM'),
            'greeting' => $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches'),
            'shift' => $hour >= 6 && $hour < 14 ? 'T1' : ($hour >= 14 && $hour < 22 ? 'T2' : 'T3'),
            'routes' => [
                'home' => route('home'),
                'login' => route('login.attempt'),
                'logout' => route('logout'),
                'warehouse' => route('carmen.warehouse'),
                'api' => url('/carmen/api'),
            ],
        ];
    }
}
