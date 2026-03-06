<?php

declare(strict_types=1);

use App\Services\NonClosureImportService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$xlsxPath = $argv[1] ?? null;
$accountId = isset($argv[2]) ? (int) $argv[2] : 1;
$userId = isset($argv[3]) ? (int) $argv[3] : 1;
$dryRun = in_array('--dry-run', $argv, true);

if (!$xlsxPath) {
    fwrite(STDERR, "Использование:\n");
    fwrite(STDERR, "  php tools/import_nonclosures_from_xlsx.php /absolute/path/to/file.xlsx [account_id] [user_id] [--dry-run]\n");
    exit(1);
}

if (!is_file($xlsxPath)) {
    fwrite(STDERR, "Файл не найден: {$xlsxPath}\n");
    exit(1);
}

/** @var NonClosureImportService $service */
$service = app(NonClosureImportService::class);
$summary = $service->summarizeWorkbook($xlsxPath);

echo "Сводка по файлу:\n";
foreach ($summary['sheet_counts'] as $sheet => $count) {
    echo " - {$sheet}: {$count}\n";
}
echo "Итого строк к импорту: {$summary['total']}\n\n";

if ($dryRun) {
    echo "Dry-run: импорт в БД не выполнялся.\n";
    exit(0);
}

$result = $service->importFromPath($xlsxPath, $accountId, $userId);

echo "Импорт завершён:\n";
echo " - добавлено: {$result['imported']}\n";
echo " - обновлено: {$result['updated']}\n";
echo " - всего обработано: {$result['total']}\n";
