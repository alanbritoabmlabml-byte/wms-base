<?php

namespace Tests\Feature;

use App\Data\MovementData;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\LocationInactiveException;
use App\Exceptions\Domain\LocationNotInWarehouseException;
use App\Exceptions\Domain\MixingNotAllowedException;
use App\Exceptions\Domain\ReasonCodeRequiredException;
use App\Models\Item;
use App\Models\Lot;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\WmsFixtures;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    use RefreshDatabase, WmsFixtures;

    private StockLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWms();
        $this->ledger = app(StockLedger::class);
    }

    #[Test]
    public function postear_dos_veces_el_mismo_client_uuid_deja_un_solo_movimiento(): void
    {
        $clientUuid = (string) Str::uuid();

        $first = $this->ledger->post($this->reception($clientUuid, 25));
        $second = $this->ledger->post($this->reception($clientUuid, 25));

        $this->assertSame($first->id, $second->id, 'El reintento debe devolver el movimiento original');
        $this->assertSame(1, StockMovement::where('client_uuid', $clientUuid)->count());

        // Y sobre todo: el saldo NO se duplica.
        $this->assertEqualsWithDelta(
            25.0,
            $this->ledger->currentQty($this->warehouse->id, $this->locationA->id, $this->item->id, $this->lot->id),
            0.0001,
        );
    }

    #[Test]
    public function stock_insuficiente_lanza_el_error_y_no_deja_movimiento_huerfano(): void
    {
        $this->ledger->post($this->reception((string) Str::uuid(), 10));

        $movementsBefore = StockMovement::count();

        try {
            $this->ledger->post(new MovementData(
                clientUuid: (string) Str::uuid(),
                type: 'PICKING',
                warehouseId: $this->warehouse->id,
                itemId: $this->item->id,
                lotId: $this->lot->id,
                qty: 30,
                uomId: $this->uom->id,
                userId: $this->user->id,
                fromLocationId: $this->locationA->id,
            ));

            $this->fail('Debio lanzar InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame('INSUFFICIENT_STOCK', $e->errorCode);
            $this->assertSame(10.0, $e->details['available']);
            $this->assertSame(30.0, $e->details['requested']);
        }

        // Rollback completo: ni movimiento nuevo ni saldo tocado.
        $this->assertSame($movementsBefore, StockMovement::count());
        $this->assertEqualsWithDelta(
            10.0,
            $this->ledger->currentQty($this->warehouse->id, $this->locationA->id, $this->item->id, $this->lot->id),
            0.0001,
        );
    }

    #[Test]
    public function una_reubicacion_mueve_el_saldo_y_no_cambia_el_total_del_almacen(): void
    {
        $this->ledger->post($this->reception((string) Str::uuid(), 40));

        $totalBefore = (float) StockBalance::where('warehouse_id', $this->warehouse->id)->sum('qty');

        $this->ledger->post(new MovementData(
            clientUuid: (string) Str::uuid(),
            type: 'REUBICACION',
            warehouseId: $this->warehouse->id,
            itemId: $this->item->id,
            lotId: $this->lot->id,
            qty: 15,
            uomId: $this->uom->id,
            userId: $this->user->id,
            fromLocationId: $this->locationA->id,
            toLocationId: $this->locationB->id,
        ));

        $qtyA = $this->ledger->currentQty($this->warehouse->id, $this->locationA->id, $this->item->id, $this->lot->id);
        $qtyB = $this->ledger->currentQty($this->warehouse->id, $this->locationB->id, $this->item->id, $this->lot->id);

        $this->assertEqualsWithDelta(25.0, $qtyA, 0.0001);
        $this->assertEqualsWithDelta(15.0, $qtyB, 0.0001);

        $totalAfter = (float) StockBalance::where('warehouse_id', $this->warehouse->id)->sum('qty');
        $this->assertEqualsWithDelta($totalBefore, $totalAfter, 0.0001, 'Una reubicacion no crea ni destruye stock');
    }

    #[Test]
    public function rebuild_balance_coincide_con_la_proyeccion_tras_una_serie_de_movimientos(): void
    {
        $this->ledger->post($this->reception((string) Str::uuid(), 100));
        $this->ledger->post($this->reception((string) Str::uuid(), 35));

        $this->ledger->post(new MovementData(
            clientUuid: (string) Str::uuid(),
            type: 'REUBICACION',
            warehouseId: $this->warehouse->id,
            itemId: $this->item->id,
            lotId: $this->lot->id,
            qty: 60,
            uomId: $this->uom->id,
            userId: $this->user->id,
            fromLocationId: $this->locationA->id,
            toLocationId: $this->locationB->id,
        ));

        $this->ledger->post(new MovementData(
            clientUuid: (string) Str::uuid(),
            type: 'PICKING',
            warehouseId: $this->warehouse->id,
            itemId: $this->item->id,
            lotId: $this->lot->id,
            qty: 20,
            uomId: $this->uom->id,
            userId: $this->user->id,
            fromLocationId: $this->locationB->id,
        ));

        $this->ledger->post(new MovementData(
            clientUuid: (string) Str::uuid(),
            type: 'AJUSTE_NEG',
            warehouseId: $this->warehouse->id,
            itemId: $this->item->id,
            lotId: $this->lot->id,
            qty: 5,
            uomId: $this->uom->id,
            userId: $this->user->id,
            fromLocationId: $this->locationA->id,
            reasonCodeId: $this->reason->id,
        ));

        foreach ([$this->locationA, $this->locationB] as $location) {
            $projected = $this->ledger->currentQty(
                $this->warehouse->id, $location->id, $this->item->id, $this->lot->id,
            );

            $rebuilt = $this->ledger->rebuildBalance(
                $this->warehouse->id, $location->id, $this->item->id, $this->lot->id,
            );

            $this->assertEqualsWithDelta(
                $projected,
                $rebuilt,
                0.0001,
                "El kardex no cuadra con el saldo en {$location->code}",
            );
        }

        // Numeros concretos, no solo coherencia interna.
        $this->assertEqualsWithDelta(70.0, $this->ledger->rebuildBalance(
            $this->warehouse->id, $this->locationA->id, $this->item->id, $this->lot->id,
        ), 0.0001);

        $this->assertEqualsWithDelta(40.0, $this->ledger->rebuildBalance(
            $this->warehouse->id, $this->locationB->id, $this->item->id, $this->lot->id,
        ), 0.0001);
    }

    #[Test]
    public function un_ajuste_sin_motivo_es_rechazado(): void
    {
        $this->expectException(ReasonCodeRequiredException::class);

        $this->ledger->post(new MovementData(
            clientUuid: (string) Str::uuid(),
            type: 'AJUSTE_POS',
            warehouseId: $this->warehouse->id,
            itemId: $this->item->id,
            lotId: $this->lot->id,
            qty: 5,
            uomId: $this->uom->id,
            userId: $this->user->id,
            toLocationId: $this->locationA->id,
        ));
    }

    #[Test]
    public function una_ubicacion_de_otro_almacen_es_rechazada(): void
    {
        $otherWarehouse = \App\Models\Warehouse::create([
            'branch_id' => $this->warehouse->branch_id,
            'code' => 'MP',
            'name' => 'Materia Prima',
            'type' => 'MP',
        ]);

        $otherZone = \App\Models\Zone::create([
            'warehouse_id' => $otherWarehouse->id,
            'code' => 'E3A',
            'name' => 'Estanteria E3A',
            'type' => 'ALMACENAJE',
        ]);

        $foreign = \App\Models\Location::create([
            'warehouse_id' => $otherWarehouse->id,
            'zone_id' => $otherZone->id,
            'code' => 'E3A-C01-N01',
            'barcode' => 'E3A-C01-N01',
        ]);

        $this->expectException(LocationNotInWarehouseException::class);

        $this->ledger->post($this->reception((string) Str::uuid(), 5, $foreign->id));
    }

    #[Test]
    public function una_ubicacion_inactiva_es_rechazada(): void
    {
        $inactive = $this->makeLocation('E1A-C03-N01', 1030, true, false);

        $this->expectException(LocationInactiveException::class);

        $this->ledger->post($this->reception((string) Str::uuid(), 5, $inactive->id));
    }

    #[Test]
    public function una_ubicacion_que_no_admite_mezcla_rechaza_un_segundo_item(): void
    {
        $exclusive = $this->makeLocation('E1A-C04-N03', 1043, mixing: false);

        $this->ledger->post($this->reception((string) Str::uuid(), 10, $exclusive->id));

        $otherItem = Item::create([
            'sku' => '5T2010201560',
            'name' => 'Bolsa 24 X 42 RAYADA PC',
            'base_uom_id' => $this->uom->id,
            'tracks_lot' => false,
        ]);

        $otherLot = Lot::create(['item_id' => $otherItem->id, 'code' => Lot::NONE]);

        $this->expectException(MixingNotAllowedException::class);

        $this->ledger->post(new MovementData(
            clientUuid: (string) Str::uuid(),
            type: 'RECEPCION',
            warehouseId: $this->warehouse->id,
            itemId: $otherItem->id,
            lotId: $otherLot->id,
            qty: 3,
            uomId: $this->uom->id,
            userId: $this->user->id,
            toLocationId: $exclusive->id,
        ));
    }

    private function reception(string $clientUuid, float $qty, ?int $locationId = null): MovementData
    {
        return new MovementData(
            clientUuid: $clientUuid,
            type: 'RECEPCION',
            warehouseId: $this->warehouse->id,
            itemId: $this->item->id,
            lotId: $this->lot->id,
            qty: $qty,
            uomId: $this->uom->id,
            userId: $this->user->id,
            toLocationId: $locationId ?? $this->locationA->id,
            toStatus: 'BUENO',
        );
    }
}
