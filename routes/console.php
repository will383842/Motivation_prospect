<?php

use Illuminate\Support\Facades\Schedule;

// ─── Séquences ───
Schedule::command('sequences:advance')->everyMinute()->onOneServer()->withoutOverlapping();

// ─── Cycle de vie & Fatigue ───
Schedule::command('lifecycle:process')->dailyAt('04:00')->onOneServer()->withoutOverlapping(60);
Schedule::command('fatigue:calculate')->everySixHours()->onOneServer()->withoutOverlapping(120);

// ─── Suivi des conversions ───
Schedule::command('conversions:reconcile')->dailyAt('06:00')->onOneServer()->withoutOverlapping(120);
