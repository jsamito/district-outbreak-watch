<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('surveillance:detect-outbreaks')->hourly()->withoutOverlapping();
Schedule::command('surveillance:escalate-alerts')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('surveillance:send-digest')->dailyAt('07:00');
Schedule::command('surveillance:check-quality')->dailyAt('05:30');
