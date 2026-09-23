<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\CarmenData;
use App\Support\CarmenSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Carmen WMS — escritorio y colector.
 *
 * La interfaz es la misma del artifact (mismas pantallas, mismo modo colector);
 * Laravel entrega la página, autentica al usuario, sirve los datos desde las
 * tablas cw_* y guarda los cambios que la interfaz hace.
 */
class CarmenController extends Controller
{
    /** Datasets que la interfaz puede modificar. */
    private const WRITABLE = ['INGRESOS', 'PEDIDOS', 'DESPACHOS', 'CONTEOS', 'IMPORT_HISTORY'];

    public function app(Request $request): View
    {
        $ready = Schema::hasTable('cw_warehouses');

        $warehouses = $ready
            ? DB::table('cw_warehouses')->where('wh_type', '!=', 'TR')->orderBy('position')->get(['code', 'name'])
                ->map(fn ($w) => ['id' => $w->code, 'name' => $w->name])->all()
            : [];

        $ira = $ready ? DB::table('cw_conteos')->where('estado', 'Finalizado')->orderBy('position')->value('ira') : null;

        return view('carmen.app', [
            'warehouses' => $warehouses,
            'stats' => [
                'warehouses' => count($warehouses),
                'ira' => $ira !== null ? number_format($ira * 100, 1, ',', '.').' %' : '—',
                'online' => $ready ? DB::table('cw_devices')->where('online', true)->count() : 0,
            ],
            'version' => substr(md5((string) @filemtime(public_path('carmen/app.js'))), 0, 8),
        ]);
    }

    /** Script con las constantes de datos que usa la interfaz (WAREHOUSES, STOCK, PEDIDOS…). */
    public function data(Request $request): Response
    {
        $user = $request->user();
        $datasets = [];

        if ($user && Schema::hasTable('cw_warehouses')) {
            foreach (CarmenSchema::DATASETS as $name => $def) {
                $datasets[$name] = DB::table($def['table'])->orderBy('position')->orderBy('id')->get()
                    ->map(fn ($row) => CarmenData::fromRow($name, $row))->all();
            }
        }

        $body = CarmenData::script($datasets, $this->session($request));

        return response($body, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string'],
            'wh' => ['nullable', 'string', 'max:20'],
        ], [], ['username' => 'usuario', 'password' => 'contraseña']);

        if (! Auth::attempt(['username' => $data['username'], 'password' => $data['password'], 'is_active' => true])) {
            throw ValidationException::withMessages(['username' => 'Usuario o contraseña incorrectos, o usuario deshabilitado.']);
        }

        $user = $request->user();
        if (($user->client_type ?? 'AMBOS') === 'COLECTOR') {
            Auth::logout();
            throw ValidationException::withMessages(['username' => 'Este usuario solo tiene acceso desde el colector.']);
        }

        $request->session()->regenerate();
        $request->session()->put('cw.wh', $this->allowedWarehouse($user->username, $data['wh'] ?? null));
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
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

    /** Crea o actualiza un registro por su clave natural (nro de ingreso, de pedido…). */
    public function upsert(Request $request, string $dataset, string $key): JsonResponse
    {
        $this->assertWritable($dataset);
        $def = CarmenSchema::DATASETS[$dataset];
        $keyColumn = CarmenData::keyColumn($dataset);
        abort_if($keyColumn === null, 422, 'Este conjunto no tiene clave.');

        $record = $request->json()->all();
        $record[$def['key']] = $key;

        $table = DB::table($def['table']);
        $existing = $table->where($keyColumn, $key)->first();
        $row = CarmenData::toRow($dataset, $record, $existing->position ?? ((int) DB::table($def['table'])->max('position') + 1));

        if ($existing) {
            DB::table($def['table'])->where('id', $existing->id)->update($row + ['updated_at' => now()]);
        } else {
            DB::table($def['table'])->insert($row + ['created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    /** Agrega un registro sin clave (historial de importaciones) al inicio de la lista. */
    public function append(Request $request, string $dataset): JsonResponse
    {
        $this->assertWritable($dataset);
        $table = CarmenSchema::DATASETS[$dataset]['table'];
        $position = (int) DB::table($table)->min('position') - 1;
        DB::table($table)->insert(CarmenData::toRow($dataset, $request->json()->all(), $position) + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true], 201);
    }

    // -----------------------------------------------------------------

    private function assertWritable(string $dataset): void
    {
        abort_unless(in_array($dataset, self::WRITABLE, true) && isset(CarmenSchema::DATASETS[$dataset]), 404);
    }

    /** Almacén pedido si el usuario lo tiene asignado; si no, el primero que tenga. */
    private function allowedWarehouse(string $username, ?string $wanted): string
    {
        $profile = Schema::hasTable('cw_users_desk') ? DB::table('cw_users_desk')->where('username', $username)->first() : null;
        $allowed = $profile ? (json_decode($profile->wh, true) ?: []) : [];
        if (! $allowed && Schema::hasTable('cw_warehouses')) {
            $allowed = DB::table('cw_warehouses')->where('wh_type', '!=', 'TR')->orderBy('position')->pluck('code')->all();
        }

        return in_array($wanted, $allowed, true) ? $wanted : ($allowed[0] ?? 'BOL2');
    }

    private function session(Request $request): array
    {
        $user = $request->user();
        $now = Carbon::now()->locale('es');
        $hour = (int) $now->format('G');

        $profile = $user && Schema::hasTable('cw_users_desk') ? DB::table('cw_users_desk')->where('username', $user->username)->first() : null;
        $device = $user && Schema::hasTable('cw_devices') ? DB::table('cw_devices')->where('username', $user->username)->value('code') : null;

        return [
            'csrf' => csrf_token(),
            'user' => $user ? [
                'user' => $user->username,
                'nombre' => $profile->nombre ?? $user->name,
                'first' => explode(' ', trim($profile->nombre ?? $user->name))[0],
                'rol' => $profile->rol ?? 'Usuario',
                'wh' => $profile ? (json_decode($profile->wh, true) ?: []) : [],
                'since' => $user->last_login_at ? 'hoy '.$user->last_login_at->format('H:i') : 'hoy',
            ] : null,
            'wh' => $request->session()->get('cw.wh'),
            'device' => $device ?? 'TC52-004',
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
