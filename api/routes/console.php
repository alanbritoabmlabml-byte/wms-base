<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('El saldo es una proyeccion; la verdad es el kardex.');
})->purpose('Recordatorio de la regla de oro del WMS');

/*
| Carga los datos iniciales de Carmen WMS solo si las tablas están vacías.
| Lo usa el arranque del contenedor en Render (docker/entrypoint.sh).
*/
Artisan::command('carmen:seed-if-empty', function () {
    if (\Illuminate\Support\Facades\DB::table('cw_warehouses')->exists()) {
        $this->info('Carmen WMS ya tiene datos; no se siembra de nuevo.');

        return 0;
    }

    $this->call('db:seed', ['--class' => \Database\Seeders\CarmenSeeder::class, '--force' => true]);

    return 0;
})->purpose('Sembrar Carmen WMS en una base vacía');
