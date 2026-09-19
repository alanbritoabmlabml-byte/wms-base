<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\Receipt;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockLedger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_seeder_deja_una_planta_completa_y_el_kardex_cuadrado(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, Warehouse::count());
        $this->assertGreaterThanOrEqual(40, Item::count());
        $this->assertGreaterThanOrEqual(280, Location::count());
        $this->assertSame(3, Receipt::where('status', 'ABIERTA')->count());
        $this->assertDatabaseHas('receipts', ['number' => 'Y8232752']);

        foreach (['admin', 'amoscoso', 'operador1'] as $username) {
            $this->assertDatabaseHas('users', ['username' => $username]);
        }

        $this->assertSame(5, User::where('username', 'admin')->first()->warehouses()->count());
        $this->assertSame(2, User::where('username', 'amoscoso')->first()->warehouses()->count());
        $this->assertSame(1, User::where('username', 'operador1')->first()->warehouses()->count());

        // Los saldos se sembraron por el ledger: el kardex reproduce cada fila.
        $ledger = app(StockLedger::class);

        StockBalance::query()->where('qty', '>', 0)->get()->each(function (StockBalance $balance) use ($ledger) {
            $this->assertEqualsWithDelta(
                (float) $balance->qty,
                $ledger->rebuildBalance(
                    (int) $balance->warehouse_id,
                    (int) $balance->location_id,
                    (int) $balance->item_id,
                    (int) $balance->lot_id,
                    (string) $balance->status,
                ),
                0.0001,
                'El kardex no reproduce el saldo sembrado',
            );
        });
    }

    #[Test]
    public function el_login_del_seeder_funciona_con_la_clave_documentada(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'amoscoso',
            'password' => 'wms1234',
            'device_serial' => '17245522504321',
        ])
            ->assertOk()
            ->assertJsonPath('user.username', 'amoscoso')
            ->assertJsonCount(2, 'warehouses');
    }
}
