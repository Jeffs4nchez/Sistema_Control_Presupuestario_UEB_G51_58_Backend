<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Crea la cédula del año siguiente el 1 de enero a medianoche
Schedule::command('cedula:next-year')->yearlyOn(1, 1, '00:00');
