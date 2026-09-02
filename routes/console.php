<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('libur:sync ' . now()->year)->yearlyOn(1, 1, '00:05');
Schedule::command('libur:sync ' . (now()->year + 1))->yearlyOn(1, 1, '00:10');
