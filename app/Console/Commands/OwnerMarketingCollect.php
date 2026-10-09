<?php

namespace App\Console\Commands;

use App\Services\Owner\Marketing\MarketingCollector;
use Illuminate\Console\Command;

class OwnerMarketingCollect extends Command
{
    protected $signature = 'owner:marketing-collect {--days=14 : сколько последних дней перезабрать} {--source=* : direct|vk|avito|sheet}';

    protected $description = 'Реклама потолков для сводки владельца: VK Реклама, Яндекс Директ, Авито, таблица заявок';

    public function handle(MarketingCollector $collector): int
    {
        foreach ($collector->run((int) $this->option('days'), (array) $this->option('source')) as $source => $line) {
            $this->line(str_pad($source, 7).' '.$line);
        }

        return self::SUCCESS;
    }
}
