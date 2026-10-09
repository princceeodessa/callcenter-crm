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

        // Сводка владельца: реклама потолков (VK Реклама, Яндекс Директ) — каждые 2 часа. Авито — своим таймером
        // systemd раз в час (deploy/ubuntu/callcenter-crm-avito-stats.timer): лимит его статистики — запрос в минуту,
        // сбор идёт 3–4 минуты и задержал бы здесь опрос чатов.
        $schedule->command('owner:marketing-collect --source=direct --source=vk')
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
