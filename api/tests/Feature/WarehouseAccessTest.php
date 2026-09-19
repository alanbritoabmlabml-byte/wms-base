<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\WmsFixtures;
use Tests\TestCase;

class WarehouseAccessTest extends TestCase
{
    use RefreshDatabase, WmsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWms();
    }

    #[Test]
    public function un_usuario_sin_asignacion_al_almacen_recibe_403(): void
    {
        $intruso = $this->makeUser('intruso', null);

        Sanctum::actingAs($intruso);

        $response = $this->getJson('/api/v1/sync/catalog?warehouse_id='.$this->warehouse->id);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    #[Test]
    public function tampoco_puede_leer_stock_de_una_ubicacion_de_ese_almacen(): void
    {
        $intruso = $this->makeUser('intruso2', null);

        Sanctum::actingAs($intruso);

        // El almacen se deduce de la ubicacion: no alcanza con omitir warehouse_id.
        $this->getJson('/api/v1/stock/by-location/'.$this->locationA->id)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    #[Test]
    public function tampoco_puede_postear_movimientos_en_ese_almacen(): void
    {
        $intruso = $this->makeUser('intruso3', null);

        Sanctum::actingAs($intruso);

        $this->postJson('/api/v1/movements', [
            'client_uuid' => '0f2c1c9e-1111-4111-8111-111111111111',
            'type' => 'REUBICACION',
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'from_location_id' => $this->locationA->id,
            'to_location_id' => $this->locationB->id,
            'qty' => 1,
        ])->assertStatus(403);
    }

    #[Test]
    public function un_usuario_asignado_a_otro_almacen_no_ve_el_ajeno(): void
    {
        $otherWarehouse = Warehouse::create([
            'branch_id' => Branch::first()->id,
            'code' => 'REPUESTOS',
            'name' => 'Repuestos',
            'type' => 'REPUESTOS',
        ]);

        $ajeno = $this->makeUser('repuestero', 'OPERADOR', $otherWarehouse);

        Sanctum::actingAs($ajeno);

        $this->getJson('/api/v1/receipts?warehouse_id='.$this->warehouse->id)->assertStatus(403);
        $this->getJson('/api/v1/receipts?warehouse_id='.$otherWarehouse->id)->assertOk();
    }

    #[Test]
    public function sin_token_la_api_responde_unauthenticated(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function health_responde_sin_token(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('db', 'ok');
    }
}
