<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('El saldo es una proyeccion; la verdad es el kardex.');
})->purpose('Recordatorio de la regla de oro del WMS');
