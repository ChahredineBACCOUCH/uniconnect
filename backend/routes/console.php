<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SCRUM-88 : chaque nuit, les invitations en attente dont la date est passée deviennent EXPIREE.
Schedule::command('invitations:expirer')->dailyAt('02:00');
