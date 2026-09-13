<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Two-factor housekeeping: prune expired verified sessions and audit
// attempts past the retention window (see config/two-factor.php).
Schedule::command('two-factor:prune')->daily();
