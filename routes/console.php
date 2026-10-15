<?php

use App\Models\OtpVerification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:send-expiry-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground();

// After the expiry reminders, which still send a trial's "expired" notice (PO-22)
Schedule::command('subscriptions:close-expired-trials')
    ->dailyAt('10:00')
    ->withoutOverlapping();

Schedule::command('compliance:send-digest-reminders')
    ->dailyAt('09:30')
    ->withoutOverlapping()
    ->runInBackground();

// Support access must end on time (04 §4.1); ResolveTenant also ends it lazily
Schedule::command('support-sessions:expire')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('model:prune', ['--model' => [OtpVerification::class]])
    ->daily()
    ->withoutOverlapping();
