<?php

use Illuminate\Support\Facades\Schedule;

// Commands live in app/Console/Commands:
//   app:create-admin    a system administrator for /admin
//   tenders:alerts      email and WhatsApp alerts for newly published tenders
//   tenders:digest      the weekly digest for companies on the free plan
//   tenders:lifecycle   emails to companies about their subscription (renewal due, ended)
//
// The server's cron runs "php artisan schedule:run" every minute.
Schedule::command('tenders:alerts')->everyTenMinutes()->withoutOverlapping();

// Saturday morning, Kabul time: the first working day of the week
Schedule::command('tenders:digest')->weeklyOn(6, '08:00')->timezone('Asia/Kabul')->withoutOverlapping();

Schedule::command('tenders:lifecycle')->hourly()->withoutOverlapping();

// the visitor figures: the location database is renewed every month, old visits are deleted
Schedule::command('geoip:update')->monthlyOn(5, '03:20')->timezone('Asia/Kabul')->withoutOverlapping();
Schedule::command('visits:prune')->dailyAt('03:40')->timezone('Asia/Kabul')->withoutOverlapping();
