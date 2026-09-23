<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\CarmenData;
use App\Support\CarmenSchema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga en las tablas cw_* exactamente los mismos datos del artifact Carmen WMS
 * (almacenes, racks E1–E6, productos, saldos, clientes, choferes, camiones,
 * usuarios, colectores, ingresos, pedidos, despachos, kardex y conteos) y crea
 * las cuentas de acceso de escritorio y colector.
 *
 * Contraseña de todos los usuarios de escritorio: wms1234 · PIN de colector: 1234
 */
class CarmenSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('seeders/data/carmen-demo.json')), true, 512, JSON_THROW_ON_ERROR);
        $now = now();

        foreach (CarmenSchema::DATASETS as $name => $def) {
            DB::table($def['table'])->delete();
            $rows = [];
            foreach ($data[$name] ?? [] as $i => $record) {
                $rows[] = CarmenData::toRow($name, $record, $i) + ['created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table($def['table'])->insert($chunk);
            }
        }

        foreach ($data['USERS_DESK'] as $u) {
            $user = User::firstOrNew(['username' => $u['user']]);
            $user->name = $u['nombre'];
            if (! $user->exists || ! $user->password) {
                $user->password = 'wms1234';
            }
            $user->client_type = in_array($u['user'], array_column($data['USERS_COL'], 'user'), true) ? 'AMBOS' : ($user->client_type === 'COLECTOR' ? 'AMBOS' : ($user->client_type ?: 'ESCRITORIO'));
            $user->is_active = $u['estado'] === 'HABILITADO';
            $user->save();
        }

        foreach ($data['USERS_COL'] as $u) {
            $user = User::firstOrNew(['username' => $u['user']]);
            if (! $user->exists) {
                $user->name = $u['nombre'];
                $user->password = 'wms1234';
                $user->client_type = 'COLECTOR';
            }
            $user->pin = '1234';
            $user->is_active = $u['estado'] === 'HABILITADO';
            $user->save();
        }

        $this->command?->info('Carmen WMS: '.count($data['PRODUCTS']).' productos, '.count($data['LOCATIONS']).' ubicaciones, '
            .count($data['STOCK']).' saldos, '.count($data['PEDIDOS']).' pedidos, '.count($data['INGRESOS']).' ingresos.');
    }
}
