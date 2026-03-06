<?php

use App\Models\NonClosure;
use App\Models\User;
use Illuminate\Support\Carbon;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$argv = $_SERVER['argv'] ?? [];
$accountId = isset($argv[1]) ? (int)$argv[1] : 1;
$userId = isset($argv[2]) ? (int)$argv[2] : 1;
$dryRun = in_array('--dry-run', $argv, true);

$dataFile = __DIR__ . '/nonclosures_2025_data.json';
if (!is_file($dataFile)) {
    fwrite(STDERR, "Не найден файл данных: {$dataFile}\n");
    exit(1);
}

$rows = json_decode(file_get_contents($dataFile), true);
if (!is_array($rows)) {
    fwrite(STDERR, "Не удалось прочитать JSON с данными.\n");
    exit(1);
}

$users = User::query()
    ->where('account_id', $accountId)
    ->where('is_active', true)
    ->get(['id', 'name', 'role']);

$imported = 0;
$updated = 0;

$normalize = function (?string $value): string {
    $value = trim(mb_strtolower((string) $value));
    $value = str_replace(['ё', "\n", "\r", "\t"], ['е', ' ', ' ', ' '], $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?: '';
    return $value;
};

$resolveUserId = function (?string $rawName, array $roles) use ($users, $normalize): ?int {
    $needle = $normalize($rawName);
    if ($needle === '') {
        return null;
    }

    foreach ($users as $user) {
        if (!in_array($user->role, $roles, true)) {
            continue;
        }

        $name = $normalize((string) $user->name);
        if ($name === '') {
            continue;
        }

        if ($name === $needle || str_contains($name, $needle) || str_contains($needle, $name)) {
            return (int) $user->id;
        }

        $parts = array_filter(explode(' ', $name));
        foreach ($parts as $part) {
            if ($part === $needle || str_starts_with($part, $needle) || str_starts_with($needle, $part)) {
                return (int) $user->id;
            }
        }
    }

    return null;
};

$makeHash = function (int $accountId, array $row) use ($normalize): string {
    $parts = [
        $accountId,
        $normalize($row['measurer_name'] ?? ''),
        $row['entry_date'] ?: '',
        $normalize($row['address'] ?? ''),
        $normalize($row['reason'] ?? ''),
        $row['follow_up_date'] ?: '',
    ];

    return sha1(implode('|', $parts));
};

foreach ($rows as $row) {
    $hash = $makeHash($accountId, $row);

    $payload = [
        'account_id' => $accountId,
        'entry_date' => !empty($row['entry_date']) ? Carbon::parse($row['entry_date'])->startOfDay() : null,
        'address' => $row['address'] ?? null,
        'reason' => $row['reason'] ?? null,
        'measurer_user_id' => $resolveUserId($row['measurer_name'] ?? null, ['measurer', 'admin', 'main_operator', 'operator']),
        'measurer_name' => $row['measurer_name'] ?? null,
        'responsible_user_id' => $resolveUserId($row['responsible_name'] ?? null, ['operator', 'main_operator', 'admin']),
        'responsible_name' => $row['responsible_name'] ?? null,
        'comment' => $row['comment'] ?? null,
        'follow_up_date' => !empty($row['follow_up_date']) ? Carbon::parse($row['follow_up_date'])->startOfDay() : null,
        'result_status' => null,
        'special_calculation' => $row['special_calculation'] ?? null,
        'source' => 'xlsx_embedded_import',
        'unique_hash' => $hash,
        'updated_by_user_id' => $userId,
    ];

    if ($dryRun) {
        $imported++;
        continue;
    }

    $existing = NonClosure::query()
        ->where('account_id', $accountId)
        ->where('unique_hash', $hash)
        ->first();

    if ($existing) {
        $existing->fill(array_filter($payload, fn ($v) => $v !== null && $v !== ''));
        $existing->updated_by_user_id = $userId;
        $existing->save();
        $updated++;
        continue;
    }

    $payload['created_by_user_id'] = $userId;
    NonClosure::create($payload);
    $imported++;
}

echo "Строк в наборе: " . count($rows) . PHP_EOL;
if ($dryRun) {
    echo "Dry-run: к записи в БД подготовлено {$imported} строк." . PHP_EOL;
    exit(0);
}

echo "Импорт завершён: добавлено {$imported}, обновлено {$updated}, всего обработано " . count($rows) . PHP_EOL;
