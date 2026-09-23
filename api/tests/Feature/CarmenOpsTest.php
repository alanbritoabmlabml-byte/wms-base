<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CarmenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CarmenOpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarmenSeeder::class);
    }

    private function admin(): User
    {
        return User::where('username', 'amoscoso')->first();
    }

    #[Test]
    public function un_movimiento_mueve_stock_y_deja_kardex(): void
    {
        $s = DB::table('cw_stock')->where('rack', 'E1')->where('qty', '>', 10)->first();
        $this->actingAs($this->admin())->postJson('/carmen/api/movimientos', ['wh' => 'BOL2', 'movs' => [
            ['tipo' => 'Reubicación', 'dir' => 'mv', 'codigo' => $s->codigo, 'lote' => $s->lote, 'qty' => 5, 'from' => $s->loc, 'to' => 'E7-C01-N1', 'doc' => 'MOV-1'],
        ]])->assertStatus(422); // E7 no existe

        $this->postJson('/carmen/api/movimientos', ['wh' => 'BOL2', 'movs' => [
            ['tipo' => 'Reubicación', 'dir' => 'mv', 'codigo' => $s->codigo, 'lote' => $s->lote, 'qty' => 5, 'from' => $s->loc, 'to' => 'E6-C01-N1', 'doc' => 'MOV-1'],
        ]])->assertOk()->assertJsonPath('kardex.0.to', 'E6-C01-N1');

        $this->assertSame($s->qty - 5, (int) DB::table('cw_stock')->where('id', $s->id)->value('qty'));
        $this->assertDatabaseHas('cw_stock', ['loc' => 'E6-C01-N1', 'codigo' => $s->codigo, 'lote' => $s->lote]);
        $this->assertDatabaseHas('cw_kardex', ['doc' => 'MOV-1', 'from_loc' => $s->loc, 'to_loc' => 'E6-C01-N1', 'username' => 'amoscoso']);
    }

    #[Test]
    public function no_permite_sacar_mas_stock_del_que_hay(): void
    {
        $s = DB::table('cw_stock')->where('rack', 'E2')->first();
        $this->actingAs($this->admin())->postJson('/carmen/api/movimientos', ['wh' => 'BOL2', 'movs' => [
            ['tipo' => 'Picking', 'dir' => 'out', 'codigo' => $s->codigo, 'lote' => $s->lote, 'qty' => $s->qty + 1, 'from' => $s->loc, 'to' => 'MUELLE-SAL'],
        ]])->assertStatus(422)->assertJsonPath('errors.movs.0', fn ($m) => str_contains($m, 'Stock insuficiente'));
        $this->assertSame($s->qty, (int) DB::table('cw_stock')->where('id', $s->id)->value('qty'));
    }

    #[Test]
    public function un_operador_no_puede_cambiar_maestros_ni_parametros(): void
    {
        $op = User::where('username', 'oponce')->first();
        $this->actingAs($op)->putJson('/carmen/api/PRODUCTS/5T2010101001', ['desc' => 'x'])->assertStatus(403);
        $this->putJson('/carmen/api/SETTINGS/tolerancia', ['v' => 100])->assertStatus(403);
    }

    #[Test]
    public function importa_productos_y_stock_inicial_con_errores_por_fila(): void
    {
        $this->actingAs($this->admin())->postJson('/carmen/api/importar/productos', ['mode' => 'upsert', 'wh' => 'BOL2', 'file' => 't.xlsx', 'rows' => [
            ['codigo' => '8T01', 'descripcion' => 'BOLSA A', 'um' => 'BUL', 'minimo' => '10', 'maximo' => '100', 'clase_abc' => 'B'],
            ['codigo' => '8T02', 'descripcion' => 'BOLSA B', 'um' => 'BUL', 'clase_abc' => 'Z'],
        ]])->assertOk()->assertJsonPath('ok', 1)->assertJsonPath('errors.0.row', 3);
        $this->assertDatabaseHas('cw_products', ['codigo' => '8T01', 'min_qty' => 10, 'rot' => 'B']);

        $this->postJson('/carmen/api/importar/stock_inicial', ['mode' => 'upsert', 'wh' => 'BOL2', 'rows' => [
            ['ubicacion' => 'E6-C02-N1', 'codigo' => '8T01', 'cantidad' => '40', 'lote' => 'L-1'],
            ['ubicacion' => 'NO-EXISTE', 'codigo' => '8T01', 'cantidad' => '5'],
        ]])->assertOk()->assertJsonPath('ok', 1);
        $this->assertDatabaseHas('cw_stock', ['loc' => 'E6-C02-N1', 'codigo' => '8T01', 'qty' => 40]);
        $this->assertDatabaseHas('cw_kardex', ['tipo' => 'Carga inicial', 'codigo' => '8T01', 'qty' => 40]);
        $this->assertDatabaseHas('cw_import_history', ['dataset' => 'Stock inicial', 'ok_rows' => 1, 'err_rows' => 1]);
    }

    #[Test]
    public function crea_usuario_de_colector_y_entra_con_pin(): void
    {
        $r = $this->actingAs($this->admin())->postJson('/carmen/api/usuarios', [
            'user' => 'nuevo', 'nombre' => 'Nuevo Operador', 'tipo' => 'COLECTOR', 'colRol' => 'Operador', 'colWh' => 'BOL2', 'isNew' => true,
        ])->assertOk()->json();
        $this->assertMatchesRegularExpression('/^\d{4}$/', $r['pin']);
        $this->post('/logout');
        $this->app['auth']->forgetGuards();

        $this->postJson('/login', ['username' => 'nuevo', 'password' => $r['password'] ?? 'x', 'mode' => 'desk'])->assertStatus(422);
        $this->postJson('/login', ['username' => 'nuevo', 'password' => $r['pin'], 'mode' => 'col'])->assertOk();
        $this->assertDatabaseHas('cw_users_col', ['username' => 'nuevo', 'online' => true]);
    }

    #[Test]
    public function rack_con_ubicaciones_por_lote_y_telemetria(): void
    {
        $this->actingAs($this->admin())->postJson('/carmen/api/lote/LOCATIONS', ['records' => [
            ['id' => 'Z1-C01-N1', 'rack' => 'Z1', 'fila' => 1, 'col' => 1, 'wh' => 'TAN', 'cap' => 6, 'sort' => 1, 'blocked' => false],
            ['id' => 'Z1-C01-N2', 'rack' => 'Z1', 'fila' => 2, 'col' => 1, 'wh' => 'TAN', 'cap' => 6, 'sort' => 2, 'blocked' => false],
        ]])->assertOk()->assertJsonPath('count', 2);
        $this->assertSame(2, DB::table('cw_locations')->where('rack', 'Z1')->where('wh', 'TAN')->count());

        $this->postJson('/carmen/api/telemetria', ['id' => 'TC52-004', 'bat' => 55, 'cola' => 2])->assertOk();
        $this->assertDatabaseHas('cw_devices', ['code' => 'TC52-004', 'bat' => 55, 'cola' => 2, 'online' => true]);
    }
}
