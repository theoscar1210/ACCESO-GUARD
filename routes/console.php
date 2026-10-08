<?php

use App\Models\Authorization;
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
