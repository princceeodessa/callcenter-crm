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

        // Дайджест низкого остатка склада кроссовок.
        $schedule->command('warehouse:low-stock-digest')
            ->dailyAt('09:00')
            ->withoutOverlapping();

        // Сводка владельца: реклама потолков (VK Реклама, Яндекс Директ, Авито, таблица заявок) — каждые 2 часа.
        $schedule->command('owner:marketing-collect')
            ->cron('20 */2 * * *')
            ->withoutOverlapping(30);

        // Таблица замеров — чаще: число замеров в сводке владельца берётся из неё.
        $schedule->command('owner:marketing-collect --source=sheet --days=40')
            ->everyFifteenMinutes()
            ->withoutOverlapping(10);
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
