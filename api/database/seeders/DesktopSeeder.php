<?php

namespace Database\Seeders;

use App\Http\Controllers\Web\LabelTemplateController;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Dispatch;
use App\Models\Driver;
use App\Models\Item;
use App\Models\LabelTemplate;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos del escritorio web (v0.2): clientes, transporte, parámetros, plantillas
 * de etiqueta y pedidos de ejemplo en distintos estados. No toca stock_balances:
 * las reservas se hacen con qty_allocated y el descuento real ocurre al despachar.
 */
class DesktopSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsersClientType();
        $this->seedCustomers();
        $this->seedTransport();
        $this->seedSettings();
        $this->seedLabelTemplates();
        $this->seedOrders();
        $this->seedDeviceTelemetry();

        $this->command?->info('Escritorio listo: '.Customer::count().' clientes, '.SalesOrder::count().' pedidos, '.Dispatch::count().' despachos.');
    }

    private function seedUsersClientType(): void
    {
        User::where('username', 'admin')->update(['client_type' => 'AMBOS']);
        User::where('username', 'amoscoso')->update(['client_type' => 'AMBOS', 'document_id' => '7788990']);
        User::where('username', 'operador1')->update(['client_type' => 'COLECTOR']);

        $bolsas = Warehouse::where('code', 'BOLSAS')->first();
        foreach ([['pgarcia', 'Pedro García Ortiz', 'SUPERVISOR'], ['rsuarez', 'Ronaldo Suárez Dorado', 'OPERADOR'], ['jguasace', 'Juan Pablo Guasace', 'OPERADOR']] as [$u, $n, $r]) {
            $user = User::firstOrCreate(['username' => $u], ['name' => $n, 'password' => 'wms1234', 'pin' => '1234', 'client_type' => 'COLECTOR', 'is_active' => true]);
            if ($bolsas && ! $user->warehouses()->where('warehouses.id', $bolsas->id)->exists()) {
                $user->warehouses()->attach($bolsas->id, ['role' => $r]);
            }
        }
    }

    private function seedCustomers(): void
    {
        $rows = [
            ['B0100004', 'FERRETERÍA TAROPE', '', 'Trinidad', 'Beni', '72033118', 'Ferretería'],
            ['S0200011', 'SUPERMERCADOS HIPERMAXI S.A.', '1028374012', 'Santa Cruz', 'Santa Cruz', '33456700', 'Cadena'],
            ['D0300021', 'DISTRIBUIDORA EL ALTO', '3344556011', 'El Alto', 'La Paz', '78901122', 'Distribuidor'],
            ['M0400007', 'MAYORISTA LOS POZOS', '', 'Santa Cruz', 'Santa Cruz', '70012233', 'Mayorista'],
            ['A0500015', 'AGROPECUARIA SAN JULIÁN', '4455667018', 'San Julián', 'Santa Cruz', '71234567', 'Agro'],
            ['C0600002', 'COMERCIAL COCHABAMBA LTDA.', '1122334015', 'Cochabamba', 'Cochabamba', '44556677', 'Distribuidor'],
            ['F0700033', 'FERRETERÍA EL CONSTRUCTOR', '', 'Montero', 'Santa Cruz', '76543210', 'Ferretería'],
            ['I0800009', 'IMPORTADORA TARIJA', '5566778019', 'Tarija', 'Tarija', '66778899', 'Importador'],
        ];
        foreach ($rows as [$code, $name, $nit, $city, $dpto, $phone, $channel]) {
            Customer::firstOrCreate(['code' => $code], ['name' => $name, 'tax_id' => $nit ?: null, 'city' => $city, 'department' => $dpto, 'phone' => $phone, 'channel' => $channel, 'is_active' => true]);
        }
    }

    private function seedTransport(): void
    {
        $drivers = [
            ['5891192', 'Fredy', 'Rivero Padilla', 'CAT-C', '78912233'],
            ['6242792', 'Omar', 'Ponce Mendoza', 'CAT-C', '72479200'],
            ['3915501', 'Luis', 'Choque Mamani', 'CAT-B', '70998877'],
        ];
        $ids = [];
        foreach ($drivers as [$ci, $fn, $ln, $lic, $tel]) {
            $ids[] = Driver::firstOrCreate(['document_id' => $ci], ['first_name' => $fn, 'last_name' => $ln, 'license_category' => $lic, 'phone' => $tel, 'status' => 'ACTIVO'])->id;
        }
        $vehicles = [
            ['2384-KHT', 'Camión 8 t', 'Hino', '500', 8000, 42, $ids[0]],
            ['4102-LPD', 'Camión 12 t', 'Volvo', 'VM 270', 12000, 60, $ids[1]],
            ['1875-ZRT', 'Camioneta', 'Toyota', 'Hilux', 1000, 4, $ids[2]],
        ];
        foreach ($vehicles as [$plate, $type, $brand, $model, $kg, $m3, $drv]) {
            Vehicle::firstOrCreate(['plate' => $plate], ['type' => $type, 'brand' => $brand, 'model' => $model, 'capacity_kg' => $kg, 'volume_m3' => $m3, 'driver_id' => $drv, 'status' => 'DISPONIBLE']);
        }
    }

    private function seedSettings(): void
    {
        $admin = User::where('username', 'admin')->first();
        $defaults = [
            ['reglas', 'tolerancia_ajuste_sin_aprobacion', 5], ['reglas', 'recepcion_ciega_produccion', false],
            ['putaway', 'rotacion', 'FEFO'], ['putaway', 'max_lineas_por_ola', 12],
            ['codigos', 'formato_ubicacion', '{RACK}-C{COL:2}-N{NIVEL}'], ['codigos', 'capacidad_por_ubicacion', 6], ['codigos', 'prefijo_despacho', 'DSP'],
            ['etiquetas', 'impresoras', LabelTemplateController::PRINTERS_DEFAULT],
            ['empresa', 'nombre', 'Plásticos Carmen'], ['empresa', 'direccion', 'Parque Industrial, Santa Cruz de la Sierra'],
        ];
        foreach ($defaults as [$g, $k, $v]) {
            if (! Setting::where(['group' => $g, 'key' => $k, 'warehouse_id' => null])->exists()) {
                Setting::put($g, $k, $v, $admin?->id);
            }
        }
    }

    private function seedLabelTemplates(): void
    {
        foreach (LabelTemplate::defaults() as $d) {
            LabelTemplate::firstOrCreate(['code' => $d['code']], $d + ['qr_size' => 3, 'font_scale' => 100, 'version' => 1]);
        }
    }

    private function seedOrders(): void
    {
        $wh = Warehouse::where('code', 'BOLSAS')->first();
        if (! $wh || SalesOrder::where('warehouse_id', $wh->id)->exists()) {
            return;
        }

        $customers = Customer::orderBy('code')->get();
        $balances = StockBalance::with('item')->forWarehouse($wh->id)->withStock()->where('status', 'BUENO')->orderBy('id')->get();
        if ($balances->isEmpty()) {
            return;
        }
        $picker = User::where('username', 'rsuarez')->first();
        $admin = User::where('username', 'admin')->first();

        $plan = [
            ['RECIBIDO', 'URGENTE', null, 0], ['RECIBIDO', 'NORMAL', null, 0], ['RECIBIDO', 'NORMAL', null, 0],
            ['PREPARACION', 'NORMAL', 'OLA-'.now()->format('ymd').'-0800', 1], ['PREPARACION', 'URGENTE', 'OLA-'.now()->format('ymd').'-0800', 1],
            ['VALIDADO', 'NORMAL', 'OLA-'.now()->subDay()->format('ymd').'-1500', 1],
            ['EMBALADO', 'NORMAL', 'OLA-'.now()->subDay()->format('ymd').'-1500', 1], ['EMBALADO', 'NORMAL', 'OLA-'.now()->subDay()->format('ymd').'-1500', 1],
            ['DESPACHADO', 'NORMAL', 'OLA-'.now()->subDays(2)->format('ymd').'-0900', 1],
        ];

        $bi = 0;
        $embalados = [];
        foreach ($plan as $i => [$status, $prio, $wave, $reserve]) {
            $c = $customers[$i % $customers->count()];
            $order = SalesOrder::create([
                'warehouse_id' => $wh->id, 'number' => sprintf('PED-%s%04d', now()->format('ym'), $i + 1), 'external_ref' => 'WC-'.(48210 + $i),
                'customer_id' => $c->id, 'status' => $status, 'priority' => $prio, 'wave_code' => $wave,
                'assigned_to' => $status === 'RECIBIDO' ? null : $picker?->id,
                'packages' => in_array($status, ['EMBALADO', 'DESPACHADO']) ? 3 + $i % 4 : 0,
                'ordered_at' => now()->subDays($status === 'RECIBIDO' ? 0 : 1 + $i % 3),
                'dispatched_at' => $status === 'DESPACHADO' ? now()->subDay()->setTime(16, 20) : null,
            ]);

            $nLines = 2 + $i % 3;
            for ($n = 1; $n <= $nLines; $n++) {
                $b = $balances[$bi++ % $balances->count()];
                $free = (float) $b->qty - (float) $b->qty_allocated;
                $qty = max(1, min(floor($free * 0.4), 40));
                $picked = in_array($status, ['VALIDADO', 'EMBALADO', 'DESPACHADO']) ? $qty : 0;
                $order->lines()->create([
                    'line_no' => $n, 'item_id' => $b->item_id, 'lot_id' => $reserve ? $b->lot_id : null,
                    'from_location_id' => $reserve ? $b->location_id : null,
                    'qty_ordered' => $qty, 'qty_allocated' => $reserve && $status !== 'DESPACHADO' ? $qty : 0, 'qty_picked' => $picked,
                    'uom_id' => $b->item->base_uom_id,
                ]);
                if ($reserve && $status !== 'DESPACHADO') {
                    StockBalance::whereKey($b->id)->increment('qty_allocated', $qty);
                }
            }
            if ($status === 'EMBALADO') {
                $embalados[] = $order;
            }
        }

        // Un despacho cargando con los dos embalados
        if ($embalados && $admin) {
            $veh = Vehicle::where('plate', '2384-KHT')->first();
            $d = Dispatch::create([
                'warehouse_id' => $wh->id, 'number' => sprintf('DSP-%s%04d', now()->format('ym'), 1),
                'vehicle_id' => $veh?->id, 'driver_id' => $veh?->driver_id, 'destination' => 'Santa Cruz / Montero',
                'status' => 'CARGANDO', 'packages' => collect($embalados)->sum('packages'), 'scheduled_at' => now()->setTime(14, 0), 'created_by' => $admin->id,
            ]);
            foreach ($embalados as $o) {
                $d->orders()->attach($o->id, ['packages_verified' => 0]);
            }
        }
    }

    private function seedDeviceTelemetry(): void
    {
        $ops = User::whereIn('username', ['rsuarez', 'operador1'])->get()->keyBy('username');
        Device::where('serial', '17245522504321')->update(['user_id' => $ops['rsuarez']->id ?? null, 'battery_pct' => 78, 'pending_queue' => 0, 'os_version' => '11', 'app_version' => '0.2.0', 'last_seen_at' => Carbon::now()->subMinutes(2)]);
        Device::where('serial', '17245522509988')->update(['user_id' => $ops['operador1']->id ?? null, 'battery_pct' => 23, 'pending_queue' => 14, 'os_version' => '10', 'app_version' => '0.1.9', 'last_seen_at' => Carbon::now()->subHours(3)]);
    }
}
