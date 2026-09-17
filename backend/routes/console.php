<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('prescriptions:purge-temporary')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('app:production-check --allow-development')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
