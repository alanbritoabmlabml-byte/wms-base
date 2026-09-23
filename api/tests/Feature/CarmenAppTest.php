<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CarmenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CarmenAppTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_portada_muestra_el_login_de_carmen_wms(): void
    {
        $this->seed(CarmenSeeder::class);

        $this->get('/')->assertOk()
            ->assertSee('Carmen WMS. Un solo sistema para todos los almacenes.', false)
            ->assertSee('Santa Cruz · Bolsas', false)
            ->assertSee('Colector (PIN)', false);
    }

    #[Test]
    public function login_y_datos_desde_la_base(): void
    {
        $this->seed(CarmenSeeder::class);

        $this->postJson('/login', ['username' => 'amoscoso', 'password' => 'wms1234', 'wh' => 'BOL2'])->assertOk();

        $js = $this->get('/carmen/datos.js')->assertOk()->getContent();
        $this->assertStringContainsString('const PRODUCTS = [{"codigo":"5T2010101001"', $js);
        $this->assertStringContainsString('"nombre":"Alan Moscoso"', $js);
        $this->assertStringContainsString('const STOCK = [', $js);
    }

    #[Test]
    public function un_usuario_solo_colector_no_entra_al_escritorio(): void
    {
        $this->seed(CarmenSeeder::class);

        $this->postJson('/login', ['username' => 'mfranco', 'password' => 'wms1234'])->assertStatus(422);
        $this->postJson('/login', ['username' => 'mfranco', 'password' => '1234', 'mode' => 'col'])->assertOk();
    }

    #[Test]
    public function guarda_los_cambios_de_un_ingreso(): void
    {
        $this->seed(CarmenSeeder::class);
        $this->actingAs(User::where('username', 'amoscoso')->first());

        $this->putJson('/carmen/api/INGRESOS/ING-2609112', [
            'fecha' => '2026-09-22', 'tipo' => 'Producción', 'origen' => 'Extrusora EX-07', 'estado' => 'Cerrado',
            'lines' => [['codigo' => '5T2010101078', 'qty' => 53, 'rec' => 53, 'lote' => 'L263504-2', 'turno' => 'T1']],
            'doc' => 'OP-4461', 'usuario' => 'rsuarez', 'muelle' => 'ING-C01-N1',
        ])->assertOk();

        $this->assertDatabaseHas('cw_ingresos', ['nro' => 'ING-2609112', 'estado' => 'Cerrado']);
        $this->putJson('/carmen/api/USERS_DESK/amoscoso', [])->assertNotFound();
    }
}
