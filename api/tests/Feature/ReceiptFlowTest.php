<?php

namespace Tests\Feature;

use App\Models\ReceiptLine;
use App\Models\StockMovement;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\WmsFixtures;
use Tests\TestCase;

class ReceiptFlowTest extends TestCase
{
    use RefreshDatabase, WmsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWms();
    }

    #[Test]
    public function recepcion_end_to_end_login_ver_nota_escanear_y_subir_el_saldo(): void
    {
        $receipt = $this->makeReceipt('Y8232752', 40);
        $line = $receipt->lines()->first();

        // 1. Login: el colector obtiene token, almacenes y permisos.
        $login = $this->postJson('/api/v1/auth/login', [
            'username' => 'operador1',
            'password' => 'wms1234',
            'device_serial' => '17245522504321',
            'app_version' => '0.1.0',
        ])->assertOk();

        $token = $login->json('token');

        $this->assertNotEmpty($token);
        $login->assertJsonPath('user.username', 'operador1');
        $login->assertJsonPath('warehouses.0.code', 'BOLSAS');
        $this->assertContains('receipt.scan', $login->json('permissions'));

        $headers = ['Authorization' => 'Bearer '.$token];

        // 2. El operador abre la nota que ya conoce.
        $this->getJson('/api/v1/receipts?warehouse_id='.$this->warehouse->id.'&status=ABIERTA,EN_PROCESO', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.number', 'Y8232752')
            ->assertJsonPath('data.0.lines_total', 1)
            ->assertJsonPath('data.0.lines_done', 0);

        $show = $this->getJson('/api/v1/receipts/'.$receipt->id, $headers)->assertOk();
        $show->assertJsonPath('data.lines.0.qty_expected', 40);
        $show->assertJsonPath('data.lines.0.qty_received', 0);
        $this->assertNotEmpty($show->json('data.lines.0.suggested_locations'));

        // 3. Primer escaneo: 25 bultos a E1A-C01-N01.
        $clientUuid = '0f2c1c9e-2222-4222-8222-222222222222';

        $scan = $this->postJson('/api/v1/receipts/'.$receipt->id.'/scans', [
            'client_uuid' => $clientUuid,
            'receipt_line_id' => $line->id,
            'item_id' => $this->item->id,
            'lot_code' => 'L2609A',
            'location_id' => $this->locationA->id,
            'qty' => 25,
            'status' => 'BUENO',
            'scanned_barcode' => '5T2010201559',
            'scanned_uom' => 'BUL',
            'occurred_at' => now()->toIso8601String(),
            'device_serial' => '17245522504321',
        ], $headers);

        $scan->assertStatus(201);
        $scan->assertJsonPath('movement.type', 'RECEPCION');
        $scan->assertJsonPath('movement.qty', 25);
        $scan->assertJsonPath('line.qty_received', 25);
        $scan->assertJsonPath('line.status', 'PARCIAL');
        $scan->assertJsonPath('balance.location', 'E1A-C01-N01');
        $scan->assertJsonPath('balance.qty', 25);

        // El saldo y qty_received subieron de verdad.
        $ledger = app(StockLedger::class);
        $this->assertEqualsWithDelta(25.0, $ledger->currentQty(
            $this->warehouse->id, $this->locationA->id, $this->item->id, $this->lot->id,
        ), 0.0001);

        $this->assertEqualsWithDelta(25.0, (float) ReceiptLine::find($line->id)->qty_received, 0.0001);

        // 4. Reintento del colector con el mismo client_uuid: 200 y sin duplicar.
        $retry = $this->postJson('/api/v1/receipts/'.$receipt->id.'/scans', [
            'client_uuid' => $clientUuid,
            'receipt_line_id' => $line->id,
            'item_id' => $this->item->id,
            'lot_code' => 'L2609A',
            'location_id' => $this->locationA->id,
            'qty' => 25,
        ], $headers);

        $retry->assertStatus(200);
        $retry->assertJsonPath('line.qty_received', 25);
        $this->assertSame(1, StockMovement::where('client_uuid', $clientUuid)->count());

        // 5. Segundo escaneo completa la linea.
        $this->postJson('/api/v1/receipts/'.$receipt->id.'/scans', [
            'client_uuid' => '0f2c1c9e-3333-4333-8333-333333333333',
            'receipt_line_id' => $line->id,
            'item_id' => $this->item->id,
            'lot_code' => 'L2609A',
            'location_id' => $this->locationB->id,
            'qty' => 15,
        ], $headers)
            ->assertStatus(201)
            ->assertJsonPath('line.qty_received', 40)
            ->assertJsonPath('line.status', 'COMPLETA');

        // 6. El operador NO puede cerrar la nota (no tiene receipt.close).
        $this->postJson('/api/v1/receipts/'.$receipt->id.'/close', [], $headers)->assertStatus(403);

        // 7. El supervisor si.
        $supervisor = $this->makeUser('amoscoso', 'SUPERVISOR');
        $supervisorToken = $supervisor->createToken('web')->plainTextToken;

        $this->postJson('/api/v1/receipts/'.$receipt->id.'/close', [], [
            'Authorization' => 'Bearer '.$supervisorToken,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'CERRADA');

        // 8. Una nota cerrada ya no admite escaneos.
        $this->postJson('/api/v1/receipts/'.$receipt->id.'/scans', [
            'client_uuid' => '0f2c1c9e-4444-4444-8444-444444444444',
            'receipt_line_id' => $line->id,
            'item_id' => $this->item->id,
            'location_id' => $this->locationA->id,
            'qty' => 1,
        ], $headers)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'RECEIPT_CLOSED');
    }

    #[Test]
    public function cerrar_con_lineas_pendientes_devuelve_409_salvo_force(): void
    {
        $receipt = $this->makeReceipt('Y8232753', 40);

        $supervisor = $this->makeUser('supervisor2', 'SUPERVISOR');
        $headers = ['Authorization' => 'Bearer '.$supervisor->createToken('web')->plainTextToken];

        $this->postJson('/api/v1/receipts/'.$receipt->id.'/close', [], $headers)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->postJson('/api/v1/receipts/'.$receipt->id.'/close', [
            'force' => true,
            'reason' => 'Produccion cerro el turno con faltante declarado',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'CERRADA');
    }

    #[Test]
    public function un_posteo_sin_client_uuid_es_rechazado(): void
    {
        $receipt = $this->makeReceipt();
        $line = $receipt->lines()->first();

        $headers = ['Authorization' => 'Bearer '.$this->user->createToken('dev')->plainTextToken];

        $this->postJson('/api/v1/receipts/'.$receipt->id.'/scans', [
            'receipt_line_id' => $line->id,
            'item_id' => $this->item->id,
            'location_id' => $this->locationA->id,
            'qty' => 5,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }
}
