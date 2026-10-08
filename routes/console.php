<?php

use App\Models\Authorization;
use App\Models\MaterialExit;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('authorizations:expire', function () {
    $count = Authorization::expired()->update(['status' => 'vencido']);

    $this->info("{$count} autorización(es) marcadas como vencidas.");
})->purpose('Marca como vencidas las autorizaciones cuya vigencia ya terminó');

Schedule::command('authorizations:expire')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('material-exits:expire', function () {
    $expired = MaterialExit::expired()->with('work')->get();

    foreach ($expired as $exit) {
        $exit->forceFill(['status' => 'vencida'])->save();
        $exit->work?->log('salida_material_vencida', null, $exit->description);
    }

    $this->info("{$expired->count()} salida(s) de material vencidas (aprobación de ".MaterialExit::VALID_HOURS.' h sin retiro).');
})->purpose('Vence las salidas de material aprobadas que nadie retiró en 48 horas');

Schedule::command('material-exits:expire')->everyFifteenMinutes()->withoutOverlapping();
