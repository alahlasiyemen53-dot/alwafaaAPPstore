<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('otp:prune')->daily();
Schedule::command('orders:auto-confirm-deliveries')->everyFiveMinutes();
Schedule::call(function (): void {
    $lifetimeMinutes = max(1, (int) config('session.lifetime', 30));
    $absoluteMinutes = (int) config('admin_session.absolute_lifetime_minutes', 0);
    $retentionMinutes = max($lifetimeMinutes, $absoluteMinutes > 0 ? $absoluteMinutes : $lifetimeMinutes);

    \Illuminate\Support\Facades\DB::table('sessions')
        ->where('last_activity', '<', now()->subMinutes($retentionMinutes)->getTimestamp())
        ->delete();
})->hourly()->name('admin-sessions-prune');
