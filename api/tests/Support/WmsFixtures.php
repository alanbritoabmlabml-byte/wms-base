<?php

namespace Tests\Support;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\Location;
use App\Models\Lot;
use App\Models\ReasonCode;
use App\Models\Receipt;
use App\Models\ReceiptLine;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;

/**
 * Montaje minimo de un almacen para los tests: mas barato y mas legible que
 * correr el seeder completo en cada caso.
 */
trait WmsFixtures
{
    protected Warehouse $warehouse;
    protected Zone $zone;
    protected Location $locationA;
    protected Location $locationB;
    protected Item $item;
    protected Lot $lot;
    protected Uom $uom;
    protected User $user;
    protected ReasonCode $reason;

    protected function bootWms(bool $allowsNegative = false): void
    {
        $company = Company::create(['code' => 'PC', 'name' => 'Plasticos Carmen S.R.L.']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'SCZ', 'name' => 'Santa Cruz']);

        $this->warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'BOLSAS',
            'name' => 'PT Bolsas',
            'type' => 'PT',
            'allows_negative_stock' => $allowsNegative,
        ]);

        $this->zone = Zone::create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'E1A',
            'name' => 'Estanteria E1A',
            'type' => 'ALMACENAJE',
        ]);

        $this->locationA = $this->makeLocation('E1A-C01-N01', 1010);
        $this->locationB = $this->makeLocation('E1A-C02-N01', 1020);

        $this->uom = Uom::create(['code' => 'BUL', 'name' => 'Bulto', 'decimals' => 0]);

        $this->item = Item::create([
            'sku' => '5T2010201559',
            'name' => 'Bolsa 28 X 50 CAFE GAVIOTA',
            'base_uom_id' => $this->uom->id,
            'category' => 'BOLSA CAMISETA',
            'tracks_lot' => true,
            'tracks_expiry' => false,
        ]);

        ItemBarcode::create([
            'item_id' => $this->item->id,
            'barcode' => $this->item->sku,
            'uom_id' => $this->uom->id,
            'qty_per_scan' => 1,
            'type' => 'INTERNO',
        ]);

        $this->lot = Lot::create(['item_id' => $this->item->id, 'code' => 'L2609A']);

        $this->reason = ReasonCode::create([
            'code' => 'INV_INI',
            'name' => 'Inventario inicial',
            'movement_type' => 'AJUSTE_POS',
        ]);

        $this->user = $this->makeUser('operador1', 'OPERADOR');
    }

    protected function makeLocation(string $code, int $sortSeq, bool $mixing = true, bool $active = true): Location
    {
        return Location::create([
            'warehouse_id' => $this->warehouse->id,
            'zone_id' => $this->zone->id,
            'code' => $code,
            'barcode' => $code,
            'sort_seq' => $sortSeq,
            'is_mixing_allowed' => $mixing,
            'is_active' => $active,
        ]);
    }

    protected function makeUser(string $username, ?string $role = 'OPERADOR', ?Warehouse $warehouse = null): User
    {
        $user = User::create([
            'name' => ucfirst($username),
            'username' => $username,
            'email' => $username.'@plasticoscarmen.com',
            'password' => 'wms1234',
            'pin' => '123456',
            'is_active' => true,
        ]);

        if ($role) {
            $user->warehouses()->attach(($warehouse ?? $this->warehouse)->id, ['role' => $role]);
        }

        return $user;
    }

    protected function makeReceipt(string $number = 'Y8232752', float $expected = 40): Receipt
    {
        $receipt = Receipt::create([
            'warehouse_id' => $this->warehouse->id,
            'number' => $number,
            'type' => 'PRODUCCION',
            'status' => 'ABIERTA',
            'expected_at' => now()->toDateString(),
        ]);

        ReceiptLine::create([
            'receipt_id' => $receipt->id,
            'line_no' => 1,
            'item_id' => $this->item->id,
            'lot_code' => $this->lot->code,
            'qty_expected' => $expected,
            'qty_received' => 0,
            'uom_id' => $this->uom->id,
            'status' => 'PENDIENTE',
        ]);

        return $receipt;
    }
}
