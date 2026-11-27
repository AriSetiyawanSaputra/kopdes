<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('kopdes:reconcile-expired-transactions')
    ->everyFiveMinutes()
    ->withoutOverlapping();

if (config('kopdes.demo_mode')) {
    Schedule::command('migrate:fresh --seed --seeder=DemoDataSeeder --force')
        ->dailyAt('00:00')
        ->timezone('Asia/Jakarta')
        ->when(fn (): bool => (bool) config('kopdes.demo_mode'))
        ->withoutOverlapping();
}
