<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Commands\DispatchQueueCheckJobsCommand;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Commands\ScheduleCheckHeartbeatCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('telescope:prune')->daily()->at('03:00');
Schedule::command('data-processing:cleanup-completed --days=7')->daily()->at('02:00');
Schedule::command('operations:generate-action-items')
    ->hourly()
    ->withoutOverlapping(55)
    ->onOneServer();
if (app()->environment('production')) {
    Schedule::command('backup:run')->weeklyOn(5)->at('23:30'); // friday
}
Schedule::command(RunHealthChecksCommand::class)->everyMinute();
Schedule::command(DispatchQueueCheckJobsCommand::class)->everyMinute();
Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyMinute();
