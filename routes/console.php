<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Unpaid card orders expire after 60 minutes (spec §4.8, Phase 10).
// Bank-transfer orders never auto-expire.
Schedule::command('payments:expire-card-orders')->hourly();
