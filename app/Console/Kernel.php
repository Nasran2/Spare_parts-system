<?php

namespace App\Console;

use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Read configured time, default 09:00, schedule daily.
        $time = Setting::get('monthly_reminder_time', '09:00');
        $schedule->command('monthly:due-reminders')->dailyAt($time);
        $schedule->command('cheques:auto-pass')->dailyAt('00:15');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
