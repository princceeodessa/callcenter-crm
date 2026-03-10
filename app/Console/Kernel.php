<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Create due task notifications.
        $schedule->command('tasks:notify-due')->everyMinute()->withoutOverlapping();

        // Pull Avito Messenger chats into CRM.
        $schedule->command('integrations:avito-poll --limit=100')
            ->everyMinute()
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
