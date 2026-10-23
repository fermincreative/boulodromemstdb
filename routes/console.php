<?php

use Illuminate\Support\Facades\Schedule;

/*
| Shared hosting runs a single cron entry:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
| The queue is drained from the scheduler instead of a long-running worker.
*/

Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('model:prune')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('db:backup')
    ->dailyAt('02:30')
    ->withoutOverlapping();
