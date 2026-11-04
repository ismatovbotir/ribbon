<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Shared hosting runs only `schedule:run` from cron; the command is
// idempotent (see seller_subscription_reminders), so overlapping/repeated
// runs are safe.
Schedule::command('subscriptions:remind')->dailyAt('09:00');
