<?php

namespace Tests\Feature;

use App\Data\MovementData;
use App\Models\Lot;
use App\Models\StockMovement;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\WmsFixtures;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase, WmsFixtures;

    private StockLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWms();
        $this->ledger = app(StockLedger::class);
        Sanctum::actingAs($this->user);
    }

    #[Test]
    public function el_catalogo_completo_baja_items_ubicaciones_uoms_y_motivos(): void
    {
        $response = $this->getJson('/api/v1/sync/catalog?warehouse_id='.$this->warehouse->id)->assertOk();

        $response->assertJsonPath('full', true);
        $response->assertJsonPath('items.0.sku', '5T2010201559');
        $response->assertJsonPath('items.0.base_uom', 'BUL');
        $response->assertJsonPath('items.0.barcodes.0.barcode', '5T2010201559');
        $response->assertJsonPath('uoms.0.code', 'BUL');
        $response->assertJsonPath('reason_codes.0.code', 'INV_INI');

        $this->assertCount(2, $response->json('locations'));
    }

    #[Test]
    public function scan_resolve_distingue_ubicacion_item_y_desconocido(): void
    {
        $location = $this->postJson('/api/v1/scan/resolve', [
            'warehouse_id' => $this->warehouse->id,
            'code' => 'E1A-C01-N01',
            'context' => 'RECEPCION',
        ])->assertOk();

        $location->assertJsonPath('kind', 'LOCATION');
        $location->assertJsonPath('location.code', 'E1A-C01-N01');
        $location->assertJsonPath('location.occupancy.items', 0);

        $item = $this->postJson('/api/v1/scan/resolve', [
            'warehouse_id' => $this->warehouse->id,
            'code' => '5T2010201559',
        ])->assertOk();

        $item->assertJsonPath('kind', 'ITEM');
        $item->assertJsonPath('item.sku', '5T2010201559');
        $item->assertJsonPath('scanned.qty_per_scan', 1);

        $this->postJson('/api/v1/scan/resolve', [
            'warehouse_id' => $this->warehouse->id,
            'code' => 'NO-EXISTE-ESTE-CODIGO',
        ])->assertOk()->assertJsonPath('kind', 'UNKNOWN');
    }

    #[Test]
    public function stock_by_item_viene_ordenado_fefo(): void
    {
        // Dos lotes del mismo item: el que vence antes debe salir primero,
        // aunque este en una ubicacion mas lejana del recorrido.
        $pronto = Lot::create([
            'item_id' => $this->item->id,
            'code' => 'L-PRONTO',
            'expires_at' => now()->addDays(20)->toDateString(),
        ]);

        $tarde = Lot::create([
            'item_id' => $this->item->id,
            'code' => 'L-TARDE',
            'expires_at' => now()->addDays(400)->toDateString(),
        ]);

        $this->post_(10, $this->locationA->id, $tarde->id);
        $this->post_(7, $this->locationB->id, $pronto->id);

        $response = $this->getJson('/api/v1/stock/by-item/'.$this->item->id.'?warehouse_id='.$this->warehouse->id)
            ->assertOk();

        $this->assertSame('L-PRONTO', $response->json('data.0.lot.code'));
        $this->assertSame('L-TARDE', $response->json('data.1.lot.code'));
    }

    #[Test]
    public function stock_by_location_lista_lo_que_hay_en_la_ubicacion(): void
    {
        $this->post_(12, $this->locationA->id, $this->lot->id);

        $this->getJson('/api/v1/stock/by-location/'.$this->locationA->id)
            ->assertOk()
            ->assertJsonPath('location.code', 'E1A-C01-N01')
            ->assertJsonPath('data.0.item.sku', '5T2010201559')
            ->assertJsonPath('data.0.qty', 12)
            ->assertJsonPath('data.0.qty_available', 12);
    }

    #[Test]
    public function el_kardex_devuelve_saldo_corrido(): void
    {
        $this->post_(100, $this->locationA->id, $this->lot->id);
        $this->post_(50, $this->locationB->id, $this->lot->id);

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

        $response = $this->getJson(
            '/api/v1/stock/kardex?item_id='.$this->item->id.'&warehouse_id='.$this->warehouse->id,
        )->assertOk();

        $this->assertEqualsWithDelta(0.0, (float) $response->json('opening_balance'), 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $response->json('data.0.running_balance'), 0.0001);
        $this->assertEqualsWithDelta(150.0, (float) $response->json('data.1.running_balance'), 0.0001);
        $this->assertEqualsWithDelta(120.0, (float) $response->json('data.2.running_balance'), 0.0001);
        $this->assertEqualsWithDelta(120.0, (float) $response->json('closing_balance'), 0.0001);
        $this->assertSame(3, $response->json('meta.total'));
    }

    #[Test]
    public function una_reubicacion_por_el_endpoint_de_movimientos(): void
    {
        $this->post_(30, $this->locationA->id, $this->lot->id);

        $response = $this->postJson('/api/v1/movements', [
            'client_uuid' => (string) Str::uuid(),
            'type' => 'REUBICACION',
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'lot_code' => 'L2609A',
            'from_location_id' => $this->locationA->id,
            'to_location_id' => $this->locationB->id,
            'qty' => 12,
            'status' => 'BUENO',
        ])->assertStatus(201);

        $response->assertJsonPath('movement.type', 'REUBICACION');

        $this->assertEqualsWithDelta(18.0, $this->ledger->currentQty(
            $this->warehouse->id, $this->locationA->id, $this->item->id, $this->lot->id,
        ), 0.0001);

        $this->assertEqualsWithDelta(12.0, $this->ledger->currentQty(
            $this->warehouse->id, $this->locationB->id, $this->item->id, $this->lot->id,
        ), 0.0001);
    }

    #[Test]
    public function un_ajuste_negativo_sin_motivo_devuelve_422(): void
    {
        $this->post_(10, $this->locationA->id, $this->lot->id);

        $this->postJson('/api/v1/movements', [
            'client_uuid' => (string) Str::uuid(),
            'type' => 'AJUSTE_NEG',
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'lot_code' => 'L2609A',
            'from_location_id' => $this->locationA->id,
            'qty' => 2,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    #[Test]
    public function stock_insuficiente_por_api_devuelve_el_code_del_doc(): void
    {
        $this->post_(5, $this->locationA->id, $this->lot->id);

        $this->postJson('/api/v1/movements', [
            'client_uuid' => (string) Str::uuid(),
            'type' => 'REUBICACION',
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'lot_code' => 'L2609A',
            'from_location_id' => $this->locationA->id,
            'to_location_id' => $this->locationB->id,
            'qty' => 50,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK')
            ->assertJsonPath('error.details.available', 5)
            ->assertJsonPath('error.details.requested', 50);
    }

    #[Test]
    public function sync_batch_procesa_en_orden_y_devuelve_207(): void
    {
        $this->post_(20, $this->locationA->id, $this->lot->id);

        $receipt = $this->makeReceipt('Y8232752', 40);
        $line = $receipt->lines()->first();

        $ok = (string) Str::uuid();
        $falla = (string) Str::uuid();

        $response = $this->postJson('/api/v1/sync/batch', [
            'device_serial' => '17245522504321',
            'operations' => [
                [
                    'endpoint' => '/receipts/'.$receipt->id.'/scans',
                    'payload' => [
                        'client_uuid' => $ok,
                        'receipt_line_id' => $line->id,
                        'item_id' => $this->item->id,
                        'lot_code' => 'L2609A',
                        'location_id' => $this->locationA->id,
                        'qty' => 10,
                    ],
                ],
                [
                    'endpoint' => '/movements',
                    'payload' => [
                        'client_uuid' => $falla,
                        'type' => 'REUBICACION',
                        'warehouse_id' => $this->warehouse->id,
                        'item_id' => $this->item->id,
                        'lot_code' => 'L2609A',
                        'from_location_id' => $this->locationB->id,
                        'to_location_id' => $this->locationA->id,
                        'qty' => 999,
                    ],
                ],
            ],
        ]);

        $response->assertStatus(207);
        $response->assertJsonPath('results.0.status', 201);
        $response->assertJsonPath('results.0.client_uuid', $ok);
        $response->assertJsonPath('results.1.status', 422);
        $response->assertJsonPath('results.1.body.error.code', 'INSUFFICIENT_STOCK');

        // La que fallo no deja movimiento; la que funciono si.
        $this->assertSame(1, StockMovement::where('client_uuid', $ok)->count());
        $this->assertSame(0, StockMovement::where('client_uuid', $falla)->count());

        $this->assertDatabaseHas('sync_batches', ['status' => 'PARCIAL', 'movements_count' => 2]);
    }

    #[Test]
    public function auth_me_y_logout(): void
    {
        $token = $this->user->createToken('17245522504321')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/auth/me', $headers)
            ->assertOk()
            ->assertJsonPath('user.username', 'operador1')
            ->assertJsonPath('warehouses.0.role', 'OPERADOR');

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertStatus(204);
    }

    private function post_(float $qty, int $locationId, int $lotId): StockMovement
    {
        return $this->ledger->post(new MovementData(
            clientUuid: (string) Str::uuid(),
            type: 'RECEPCION',
            warehouseId: $this->warehouse->id,
            itemId: $this->item->id,
            lotId: $lotId,
            qty: $qty,
            uomId: $this->uom->id,
            userId: $this->user->id,
            toLocationId: $locationId,
            toStatus: 'BUENO',
        ));
    }
}
