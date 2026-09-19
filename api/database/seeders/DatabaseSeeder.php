<?php

namespace Database\Seeders;

use App\Data\MovementData;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Device;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemWarehouse;
use App\Models\Location;
use App\Models\Lot;
use App\Models\ReasonCode;
use App\Models\Receipt;
use App\Models\ReceiptLine;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\StockLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos de arranque de Plasticos Carmen S.R.L.
 *
 * Regla que se respeta aqui igual que en produccion: los saldos iniciales NO se
 * insertan a mano en `stock_balances`, se postean por StockLedger como
 * AJUSTE_POS con motivo INV_INI. Asi el kardex cuadra desde el dia cero y
 * `rebuildBalance()` da lo mismo que la proyeccion.
 */
class DatabaseSeeder extends Seeder
{
    private array $uoms = [];
    private array $warehouses = [];
    private array $zones = [];
    private array $items = [];
    private ?User $admin = null;
    private ?ReasonCode $inventarioInicial = null;

    public function run(): void
    {
        $ledger = app(StockLedger::class);

        $this->seedUoms();
        $this->seedReasonCodes();
        $company = $this->seedTopology();
        $this->seedUsers();
        $this->seedItems();
        $this->seedLocationsCatalog();
        $this->seedDevices();
        $this->seedOpeningStock($ledger);
        $this->seedReceipts();

        $this->command?->info('Seed listo: '.Location::count().' ubicaciones, '
            .Item::count().' items, '.DB::table('stock_movements')->count().' movimientos de kardex.');
    }

    // -----------------------------------------------------------------
    // Maestros base
    // -----------------------------------------------------------------

    private function seedUoms(): void
    {
        $definitions = [
            ['KG', 'Kilogramo', 3],
            ['BUL', 'Bulto', 0],
            ['UND', 'Unidad', 0],
            ['ROLLO', 'Rollo', 0],
            ['MIL', 'Millar', 0],
        ];

        foreach ($definitions as [$code, $name, $decimals]) {
            $this->uoms[$code] = Uom::create(['code' => $code, 'name' => $name, 'decimals' => $decimals]);
        }
    }

    private function seedReasonCodes(): void
    {
        $definitions = [
            ['INV_INI', 'Inventario inicial', 'AJUSTE_POS', false, true],
            ['CONTEO_POS', 'Sobrante de conteo ciclico', 'AJUSTE_POS', true, true],
            ['CONTEO_NEG', 'Faltante de conteo ciclico', 'AJUSTE_NEG', true, true],
            ['MERMA', 'Merma de produccion', 'AJUSTE_NEG', false, true],
            ['ROTURA', 'Rotura de bobina / bulto', 'AJUSTE_NEG', false, true],
            ['MUESTRA', 'Salida de muestra a comercial', 'AJUSTE_NEG', true, true],
            ['DEVOL_INT', 'Devolucion interna de planta', 'AJUSTE_POS', false, false],
        ];

        foreach ($definitions as [$code, $name, $type, $approval, $cost]) {
            $reason = ReasonCode::create([
                'code' => $code,
                'name' => $name,
                'movement_type' => $type,
                'requires_approval' => $approval,
                'affects_cost' => $cost,
            ]);

            if ($code === 'INV_INI') {
                $this->inventarioInicial = $reason;
            }
        }
    }

    private function seedTopology(): Company
    {
        $company = Company::create([
            'code' => 'PC',
            'name' => 'Plasticos Carmen S.R.L.',
            'tax_id' => '1020304056',
        ]);

        $scz = Branch::create([
            'company_id' => $company->id,
            'code' => 'SCZ',
            'name' => 'Santa Cruz',
            'timezone' => 'America/La_Paz',
        ]);

        $lpz = Branch::create([
            'company_id' => $company->id,
            'code' => 'LPZ',
            'name' => 'La Paz',
            'timezone' => 'America/La_Paz',
        ]);

        $definitions = [
            [$scz, 'BOLSAS', 'PT Bolsas', 'PT', false, true],
            [$scz, 'MP', 'Materia Prima', 'MP', false, false],
            [$scz, 'REPUESTOS', 'Repuestos y Mantenimiento', 'REPUESTOS', false, false],
            [$lpz, 'PT-LPZ', 'PT La Paz', 'PT', false, false],
            [$lpz, 'MP-LPZ', 'Materia Prima La Paz', 'MP', false, false],
        ];

        foreach ($definitions as [$branch, $code, $name, $type, $negative, $putaway]) {
            $this->warehouses[$code] = Warehouse::create([
                'branch_id' => $branch->id,
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'allows_negative_stock' => $negative,
                'requires_putaway' => $putaway,
                'is_active' => true,
            ]);
        }

        return $company;
    }

    private function seedUsers(): void
    {
        $this->admin = User::create([
            'name' => 'Administrador WMS',
            'username' => 'admin',
            'email' => 'admin@plasticoscarmen.com',
            'password' => 'wms1234',
            'pin' => '123456',
            'is_active' => true,
        ]);

        $supervisor = User::create([
            'name' => 'Alan Moscoso',
            'username' => 'amoscoso',
            'email' => 'amoscoso@plasticoscarmen.com',
            'password' => 'wms1234',
            'pin' => '123456',
            'is_active' => true,
        ]);

        $operador = User::create([
            'name' => 'Operador Bolsas 1',
            'username' => 'operador1',
            'email' => null,
            'password' => 'wms1234',
            'pin' => '123456',
            'is_active' => true,
        ]);

        foreach ($this->warehouses as $warehouse) {
            $this->admin->warehouses()->attach($warehouse->id, ['role' => 'ADMIN']);
        }

        $supervisor->warehouses()->attach($this->warehouses['BOLSAS']->id, ['role' => 'SUPERVISOR']);
        $supervisor->warehouses()->attach($this->warehouses['MP']->id, ['role' => 'SUPERVISOR']);

        $operador->warehouses()->attach($this->warehouses['BOLSAS']->id, ['role' => 'OPERADOR']);
    }

    private function seedDevices(): void
    {
        Device::create([
            'serial' => '17245522504321',
            'model' => 'TC57',
            'warehouse_id' => $this->warehouses['BOLSAS']->id,
            'app_version' => '0.1.0',
            'last_seen_at' => Carbon::now(),
        ]);

        Device::create([
            'serial' => '17245522509988',
            'model' => 'MC3300',
            'warehouse_id' => $this->warehouses['MP']->id,
            'app_version' => '0.1.0',
            'last_seen_at' => Carbon::now()->subHours(3),
        ]);
    }

    // -----------------------------------------------------------------
    // Ubicaciones
    // -----------------------------------------------------------------

    /**
     * Racks por almacen: zona => [columnas, niveles].
     * El codigo sigue la convencion {ZONA}-C{COLUMNA}-N{NIVEL} (doc 02).
     */
    private const RACK_LAYOUT = [
        'BOLSAS' => [['E1A', 12, 4], ['E1B', 12, 4], ['E2A', 12, 4], ['E2B', 12, 4]],
        'MP' => [['E3A', 10, 3], ['E3B', 10, 3]],
        'REPUESTOS' => [['E4A', 10, 2]],
        'PT-LPZ' => [['E5A', 8, 2]],
        'MP-LPZ' => [['E6A', 6, 2]],
    ];

    /** Zonas de servicio: codigo => [nombre, tipo, cantidad de ubicaciones, admite mezcla]. */
    private const SERVICE_ZONES = [
        'REC' => ['Recepcion', 'RECEPCION', 'REC', true],
        'DES' => ['Despacho', 'DESPACHO', 'DES', true],
        'CUA' => ['Cuarentena', 'CUARENTENA', 'CUA', true],
    ];

    private function seedLocationsCatalog(): void
    {
        $serviceCounts = [
            'BOLSAS' => ['REC' => 6, 'DES' => 4, 'CUA' => 2],
            'MP' => ['REC' => 4, 'DES' => 2, 'CUA' => 1],
            'REPUESTOS' => ['REC' => 2, 'DES' => 1, 'CUA' => 0],
            'PT-LPZ' => ['REC' => 2, 'DES' => 1, 'CUA' => 0],
            'MP-LPZ' => ['REC' => 2, 'DES' => 1, 'CUA' => 0],
        ];

        foreach (self::RACK_LAYOUT as $warehouseCode => $racks) {
            $warehouse = $this->warehouses[$warehouseCode];
            $zoneIndex = 0;

            foreach ($racks as [$zoneCode, $columns, $levels]) {
                $zone = Zone::create([
                    'warehouse_id' => $warehouse->id,
                    'code' => $zoneCode,
                    'name' => 'Estanteria '.$zoneCode,
                    'type' => 'ALMACENAJE',
                    'picking_priority' => 10 + $zoneIndex,
                ]);

                $this->zones[$warehouseCode][$zoneCode] = $zone;

                // Serpentin: la zona par se recorre con las columnas al reves,
                // que es como camina el montacarguista al volver por el pasillo.
                $columnOrder = range(1, $columns);

                if ($zoneIndex % 2 === 1) {
                    $columnOrder = array_reverse($columnOrder);
                }

                $step = 0;

                foreach ($columnOrder as $column) {
                    for ($level = 1; $level <= $levels; $level++) {
                        $step++;
                        $code = sprintf('%s-C%02d-N%02d', $zoneCode, $column, $level);

                        Location::create([
                            'warehouse_id' => $warehouse->id,
                            'zone_id' => $zone->id,
                            'code' => $code,
                            'barcode' => $code,
                            'aisle' => $zoneCode,
                            'rack' => sprintf('C%02d', $column),
                            'level' => sprintf('N%02d', $level),
                            'position' => null,
                            'sort_seq' => ($zoneIndex + 1) * 10000 + $step * 10,
                            'max_weight_kg' => 1200,
                            // El nivel de piso admite mezcla; los altos son de un solo item.
                            'is_mixing_allowed' => $level === 1,
                            'is_active' => true,
                        ]);
                    }
                }

                $zoneIndex++;
            }

            $this->seedServiceZones($warehouseCode, $serviceCounts[$warehouseCode], $zoneIndex);
        }
    }

    private function seedServiceZones(string $warehouseCode, array $counts, int $zoneIndex): void
    {
        $warehouse = $this->warehouses[$warehouseCode];

        foreach (self::SERVICE_ZONES as $zoneCode => [$name, $type, $prefix, $mixing]) {
            $quantity = $counts[$zoneCode] ?? 0;

            if ($quantity < 1) {
                continue;
            }

            $zone = Zone::create([
                'warehouse_id' => $warehouse->id,
                'code' => $zoneCode,
                'name' => $name,
                'type' => $type,
                'picking_priority' => 90,
            ]);

            $this->zones[$warehouseCode][$zoneCode] = $zone;

            for ($i = 1; $i <= $quantity; $i++) {
                $code = sprintf('%s-%02d', $prefix, $i);

                Location::create([
                    'warehouse_id' => $warehouse->id,
                    'zone_id' => $zone->id,
                    'code' => $code,
                    'barcode' => $code,
                    'aisle' => $prefix,
                    'sort_seq' => ($zoneIndex + 10) * 10000 + $i * 10,
                    'is_mixing_allowed' => $mixing,
                    'is_active' => true,
                ]);
            }

            $zoneIndex++;
        }
    }

    // -----------------------------------------------------------------
    // Items
    // -----------------------------------------------------------------

    /**
     * Catalogo real de planta: bolsas de polietileno (producto terminado),
     * materia prima a granel y repuestos de maquina.
     *
     * [sku, nombre, uom base, categoria, lleva lote, lleva vencimiento, almacen]
     */
    private const CATALOG = [
        // --- PT Bolsas (Santa Cruz) ---
        ['5T2010201559', 'Bolsa 28 X 50 CAFE GAVIOTA', 'BUL', 'BOLSA CAMISETA', true, false, 'BOLSAS'],
        ['5T2010201560', 'Bolsa 24 X 42 RAYADA PC', 'BUL', 'BOLSA CAMISETA', true, false, 'BOLSAS'],
        ['5T2010201561', 'BOLSA 8 X 12 COMUN COLORES', 'BUL', 'BOLSA PLANA', true, false, 'BOLSAS'],
        ['5T2010201562', 'BOLSA 65 X 80 CHINITA NEGRA', 'BUL', 'BOLSA BASURA', true, false, 'BOLSAS'],
        ['5T2010201563', 'Bolsa 35 X 65 N/MOTITA NUEVA', 'BUL', 'BOLSA CAMISETA', true, false, 'BOLSAS'],
        ['5T2010201564', 'Bolsa 17 X 34 BLANCA ECO P-90', 'BUL', 'BOLSA CAMISETA', true, false, 'BOLSAS'],
        ['5T2010201565', 'Bolsa 10X15 ROLLO PHARMANDINA', 'ROLLO', 'BOLSA ROLLO', true, true, 'BOLSAS'],
        ['5T2010201566', 'Bolsa 30 X 40 ROLLO TRAVERSO', 'ROLLO', 'BOLSA ROLLO', true, false, 'BOLSAS'],
        ['5T2010201567', 'PLATILLOS 15 P-10', 'MIL', 'DESCARTABLE', false, false, 'BOLSAS'],
        ['5T2010201568', 'Bolsa 20 X 30 CRISTAL P-100', 'BUL', 'BOLSA PLANA', true, false, 'BOLSAS'],
        ['5T2010201569', 'Bolsa 40 X 60 NEGRA REFORZADA', 'BUL', 'BOLSA BASURA', true, false, 'BOLSAS'],
        ['5T2010201570', 'Bolsa 12 X 20 COMUN BLANCA', 'BUL', 'BOLSA PLANA', true, false, 'BOLSAS'],
        ['5T2010201571', 'Bolsa 26 X 46 AZUL MERCADO', 'BUL', 'BOLSA CAMISETA', true, false, 'BOLSAS'],
        ['5T2010201572', 'Bolsa 22 X 38 VERDE GAVIOTA', 'BUL', 'BOLSA CAMISETA', true, false, 'BOLSAS'],
        ['5T2010201573', 'Bolsa 50 X 70 NEGRA INDUSTRIAL', 'BUL', 'BOLSA BASURA', true, false, 'BOLSAS'],
        ['5T2010201574', 'Bolsa 15 X 25 CRISTAL P-50', 'BUL', 'BOLSA PLANA', true, false, 'BOLSAS'],
        ['5T2010201575', 'Bolsa 32 X 55 ROJA SUPERMERCADO', 'BUL', 'BOLSA CAMISETA', true, false, 'BOLSAS'],
        ['5T2010201576', 'Bolsa 18 X 28 MOTITA CELESTE', 'BUL', 'BOLSA CAMISETA', true, false, 'BOLSAS'],
        ['5T2010201577', 'Bolsa 45 X 65 CHINITA BLANCA', 'BUL', 'BOLSA BASURA', true, false, 'BOLSAS'],
        ['5T2010201578', 'Bolsa 14 X 22 ROLLO PANADERIA', 'ROLLO', 'BOLSA ROLLO', true, true, 'BOLSAS'],
        ['5T2010201579', 'Bolsa 60 X 90 NEGRA CONTENEDOR', 'BUL', 'BOLSA BASURA', true, false, 'BOLSAS'],
        ['5T2010201580', 'Bolsa 25 X 35 TRANSPARENTE P-200', 'BUL', 'BOLSA PLANA', true, false, 'BOLSAS'],
        ['5T2010201581', 'Bolsa 30 X 50 CAFE PANADERIA', 'BUL', 'BOLSA CAMISETA', true, false, 'BOLSAS'],
        ['5T2010201582', 'Bolsa 38 X 58 GAVIOTA AMARILLA', 'BUL', 'BOLSA CAMISETA', true, false, 'BOLSAS'],
        ['5T2010201583', 'Bolsa 10 X 18 COMUN COLORES P-500', 'BUL', 'BOLSA PLANA', true, false, 'BOLSAS'],
        ['5T2010201584', 'Bolsa 55 X 75 NEGRA JARDIN', 'BUL', 'BOLSA BASURA', true, false, 'BOLSAS'],
        ['5T2010201585', 'Bolsa 16 X 30 ROLLO FRUTERIA', 'ROLLO', 'BOLSA ROLLO', true, true, 'BOLSAS'],
        ['5T2010201586', 'VASOS 10 ONZAS P-50', 'MIL', 'DESCARTABLE', false, false, 'BOLSAS'],
        ['5T2010201587', 'PLATILLOS 20 P-10', 'MIL', 'DESCARTABLE', false, false, 'BOLSAS'],
        ['5T2010201588', 'Bolsa 42 X 62 RAYADA PC GRANDE', 'BUL', 'BOLSA CAMISETA', true, false, 'BOLSAS'],

        // --- Materia prima (Santa Cruz) ---
        ['3M4010300101', 'POLIETILENO ALTA DENSIDAD', 'KG', 'RESINA', true, false, 'MP'],
        ['3M4010300102', 'POLIETILENO BAJA DENSIDAD', 'KG', 'RESINA', true, false, 'MP'],
        ['3M4010300103', 'POLIETILENO LINEAL BAJA DENSIDAD', 'KG', 'RESINA', true, false, 'MP'],
        ['3M4010300104', 'MASTERBATCH NEGRO', 'KG', 'ADITIVO', true, true, 'MP'],
        ['3M4010300105', 'MASTERBATCH BLANCO', 'KG', 'ADITIVO', true, true, 'MP'],
        ['3M4010300106', 'MASTERBATCH AZUL', 'KG', 'ADITIVO', true, true, 'MP'],
        ['3M4010300107', 'CARBONATO DE CALCIO', 'KG', 'CARGA', true, false, 'MP'],
        ['3M4010300108', 'DESLIZANTE ANTIBLOQUEO', 'KG', 'ADITIVO', true, true, 'MP'],
        ['3M4010300109', 'POLIPROPILENO HOMOPOLIMERO', 'KG', 'RESINA', true, false, 'MP'],
        ['3M4010300110', 'TINTA FLEXOGRAFICA NEGRA', 'KG', 'TINTA', true, true, 'MP'],

        // --- Repuestos (Santa Cruz) ---
        ['7R9010500201', 'RESISTENCIA CERAMICA 220V 600W', 'UND', 'REPUESTO', false, false, 'REPUESTOS'],
        ['7R9010500202', 'RODAMIENTO 6205 2RS', 'UND', 'REPUESTO', false, false, 'REPUESTOS'],
        ['7R9010500203', 'CUCHILLA SELLADORA 600MM', 'UND', 'REPUESTO', false, false, 'REPUESTOS'],
        ['7R9010500204', 'TEFLON ADHESIVO ROLLO 10M', 'ROLLO', 'REPUESTO', false, false, 'REPUESTOS'],
    ];

    private function seedItems(): void
    {
        foreach (self::CATALOG as $index => [$sku, $name, $uomCode, $category, $tracksLot, $tracksExpiry, $warehouseCode]) {
            $uom = $this->uoms[$uomCode];

            $item = Item::create([
                'sku' => $sku,
                'name' => $name,
                'base_uom_id' => $uom->id,
                'category' => $category,
                'tracks_lot' => $tracksLot,
                'tracks_expiry' => $tracksExpiry,
                'shelf_life_days' => $tracksExpiry ? 540 : null,
                'min_stock' => $uomCode === 'KG' ? 500 : 20,
                'max_stock' => $uomCode === 'KG' ? 25000 : 800,
                'is_active' => true,
            ]);

            $this->items[$sku] = ['model' => $item, 'warehouse' => $warehouseCode];

            // Codigo interno: 1 scan = 1 unidad base.
            ItemBarcode::create([
                'item_id' => $item->id,
                'barcode' => $sku,
                'uom_id' => $uom->id,
                'qty_per_scan' => 1,
                'type' => 'INTERNO',
            ]);

            // GTIN-14 del bulto para los items que ya salen etiquetados con GS1-128.
            // Escanear el GTIN del pallet suma 1 bulto; el AI 37 dira cuantos vienen.
            if ($index % 3 === 0) {
                ItemBarcode::create([
                    'item_id' => $item->id,
                    'barcode' => $this->gtin14($index),
                    'uom_id' => $uom->id,
                    'qty_per_scan' => 1,
                    'type' => 'GS1_128',
                ]);
            }

            // Codigo de caja: 1 scan = 12 unidades base. Esto es lo que evita que
            // el operador tenga que pensar cuando escanea la caja en vez de la unidad.
            if ($uomCode === 'BUL' && $index % 4 === 0) {
                ItemBarcode::create([
                    'item_id' => $item->id,
                    'barcode' => '78'.str_pad((string) (900000 + $index), 11, '0', STR_PAD_LEFT),
                    'uom_id' => $uom->id,
                    'qty_per_scan' => 12,
                    'type' => 'EAN13',
                ]);
            }

            ItemWarehouse::create([
                'item_id' => $item->id,
                'warehouse_id' => $this->warehouses[$warehouseCode]->id,
                'min_stock' => $uomCode === 'KG' ? 500 : 20,
                'max_stock' => $uomCode === 'KG' ? 25000 : 800,
                'abc_class' => ['A', 'B', 'C'][$index % 3],
            ]);
        }
    }

    /** GTIN-14 valido (13 digitos + digito verificador GS1). */
    private function gtin14(int $seed): string
    {
        $body = '750'.str_pad((string) (1234000 + $seed), 10, '0', STR_PAD_LEFT);
        $body = substr($body, 0, 13);

        $sum = 0;

        foreach (str_split($body) as $position => $digit) {
            $sum += ((int) $digit) * (($position % 2 === 0) ? 3 : 1);
        }

        $check = (10 - ($sum % 10)) % 10;

        return $body.$check;
    }

    // -----------------------------------------------------------------
    // Saldos iniciales — SIEMPRE por StockLedger
    // -----------------------------------------------------------------

    private function seedOpeningStock(StockLedger $ledger): void
    {
        $occurredAt = Carbon::now()->subDays(3)->setTime(7, 30);
        $counter = 0;
        // location_id => item_id, para respetar `is_mixing_allowed` igual que en
        // produccion: el ledger rechazaria un segundo item en un nivel exclusivo.
        $occupiedBy = [];

        foreach ($this->items as $sku => $meta) {
            /** @var Item $item */
            $item = $meta['model'];
            $warehouse = $this->warehouses[$meta['warehouse']];

            // Ubicaciones de almacenaje del almacen, en orden de recorrido.
            $locations = Location::query()
                ->where('warehouse_id', $warehouse->id)
                ->whereHas('zone', fn ($q) => $q->where('type', 'ALMACENAJE'))
                ->orderBy('sort_seq')
                ->get();

            if ($locations->isEmpty()) {
                continue;
            }

            $lots = $this->lotsFor($item, $counter);

            // Cada item ocupa entre 1 y 3 ubicaciones distintas: es lo que pasa
            // de verdad en planta y es lo que hace util la consulta by-item.
            $spread = 1 + ($counter % 3);

            for ($i = 0; $i < $spread; $i++) {
                $location = $this->pickLocation($locations, ($counter * 7 + $i * 13), (int) $item->id, $occupiedBy);

                if (! $location) {
                    continue;
                }

                $occupiedBy[$location->id] = (int) $item->id;
                $lot = $lots[$i % count($lots)];

                $qty = $item->baseUom->code === 'KG'
                    ? round(250 + (($counter * 37 + $i * 91) % 1750), 3)
                    : (float) (10 + (($counter * 11 + $i * 23) % 140));

                $ledger->post(new MovementData(
                    clientUuid: (string) Str::uuid(),
                    type: 'AJUSTE_POS',
                    warehouseId: (int) $warehouse->id,
                    itemId: (int) $item->id,
                    lotId: (int) $lot->id,
                    qty: $qty,
                    uomId: (int) $item->base_uom_id,
                    userId: (int) $this->admin->id,
                    toLocationId: (int) $location->id,
                    toStatus: 'BUENO',
                    documentType: 'AJUSTE',
                    reasonCodeId: (int) $this->inventarioInicial->id,
                    deviceId: 'SEED',
                    occurredAt: $occurredAt->copy()->addMinutes($counter),
                ));

                $counter++;
            }
        }

        // Un par de ubicaciones en cuarentena para que el estado no quede sin uso.
        $this->seedQuarantine($ledger, $occurredAt);
    }

    /**
     * Elige la primera ubicacion utilizable a partir del indice pedido: o admite
     * mezcla, o esta libre, o ya tiene este mismo item.
     *
     * @param  \Illuminate\Support\Collection<int,Location>  $locations
     * @param  array<int,int>  $occupiedBy
     */
    private function pickLocation($locations, int $offset, int $itemId, array $occupiedBy): ?Location
    {
        $count = $locations->count();

        for ($step = 0; $step < $count; $step++) {
            /** @var Location $candidate */
            $candidate = $locations[($offset + $step) % $count];
            $owner = $occupiedBy[$candidate->id] ?? null;

            if ($candidate->is_mixing_allowed || $owner === null || $owner === $itemId) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array<int,Lot> */
    private function lotsFor(Item $item, int $counter): array
    {
        if (! $item->tracks_lot) {
            return [Lot::firstOrCreate(['item_id' => $item->id, 'code' => Lot::NONE])];
        }

        $lots = [];
        $base = Carbon::now()->subDays(30 + $counter);

        for ($i = 0; $i < 2; $i++) {
            $code = sprintf('L%s%02d', $base->copy()->addDays($i * 9)->format('ymd'), $i + 1);

            $lots[] = Lot::firstOrCreate(
                ['item_id' => $item->id, 'code' => $code],
                [
                    'manufactured_at' => $base->copy()->addDays($i * 9)->toDateString(),
                    // Solo los items con vencimiento llevan fecha: asi FEFO tiene
                    // algo real que ordenar y el resto cae al final.
                    'expires_at' => $item->tracks_expiry
                        ? $base->copy()->addDays($i * 9 + ($item->shelf_life_days ?? 540))->toDateString()
                        : null,
                    'supplier_lot' => $i === 0 ? null : 'PROV-'.$code,
                ],
            );
        }

        return $lots;
    }

    private function seedQuarantine(StockLedger $ledger, Carbon $occurredAt): void
    {
        $warehouse = $this->warehouses['BOLSAS'];

        $location = Location::query()
            ->where('warehouse_id', $warehouse->id)
            ->whereHas('zone', fn ($q) => $q->where('type', 'CUARENTENA'))
            ->first();

        if (! $location) {
            return;
        }

        $item = $this->items['5T2010201562']['model'];
        $lot = Lot::where('item_id', $item->id)->firstOrFail();

        $ledger->post(new MovementData(
            clientUuid: (string) Str::uuid(),
            type: 'AJUSTE_POS',
            warehouseId: (int) $warehouse->id,
            itemId: (int) $item->id,
            lotId: (int) $lot->id,
            qty: 8,
            uomId: (int) $item->base_uom_id,
            userId: (int) $this->admin->id,
            toLocationId: (int) $location->id,
            toStatus: 'CUARENTENA',
            documentType: 'AJUSTE',
            reasonCodeId: (int) $this->inventarioInicial->id,
            deviceId: 'SEED',
            occurredAt: $occurredAt->copy()->addHours(2),
        ));
    }

    // -----------------------------------------------------------------
    // Notas de ingreso abiertas
    // -----------------------------------------------------------------

    private function seedReceipts(): void
    {
        // Y8232752 es el numero que el operador ya conoce de WorkCorp.
        $this->createReceipt('BOLSAS', 'Y8232752', 'PRODUCCION', 'WC-2026-0912', null, [
            ['5T2010201559', 40],
            ['5T2010201560', 25],
            ['5T2010201561', 60],
            ['5T2010201563', 30],
            ['5T2010201567', 15],
            ['5T2010201568', 50],
        ]);

        $this->createReceipt('BOLSAS', 'Y8232753', 'PRODUCCION', 'WC-2026-0913', null, [
            ['5T2010201562', 35],
            ['5T2010201565', 20],
            ['5T2010201569', 45],
        ]);

        $this->createReceipt('MP', 'C0045128', 'COMPRA', 'SIMEC-OC-77219', 'BRASKEM BOLIVIA S.A.', [
            ['3M4010300101', 12500],
            ['3M4010300104', 750],
            ['3M4010300107', 4000],
        ]);
    }

    private function createReceipt(
        string $warehouseCode,
        string $number,
        string $type,
        ?string $externalRef,
        ?string $supplier,
        array $lines,
    ): void {
        $receipt = Receipt::create([
            'warehouse_id' => $this->warehouses[$warehouseCode]->id,
            'number' => $number,
            'type' => $type,
            'status' => 'ABIERTA',
            'external_ref' => $externalRef,
            'supplier_name' => $supplier,
            'expected_at' => Carbon::now()->toDateString(),
        ]);

        foreach ($lines as $index => [$sku, $qty]) {
            /** @var Item $item */
            $item = $this->items[$sku]['model'];

            $lotCode = $item->tracks_lot
                ? Lot::where('item_id', $item->id)->orderBy('id')->value('code')
                : null;

            ReceiptLine::create([
                'receipt_id' => $receipt->id,
                'line_no' => $index + 1,
                'item_id' => $item->id,
                'lot_code' => $lotCode,
                'qty_expected' => $qty,
                'qty_received' => 0,
                'uom_id' => $item->base_uom_id,
                'status' => 'PENDIENTE',
            ]);
        }
    }
}
