<?php

namespace App\Support\Warehouse;

use App\Models\WarehouseProduct;

/**
 * Общая идентичность товара по артикулу: расцветка (артикул) = один товар,
 * даже если название в источнике написано по-разному. Используется и обычным
 * импортом склада, и импортом поставок закупок — чтобы оба пути не расходились
 * и не плодили дублирующиеся карточки на одну и ту же расцветку.
 */
class ArticleIdentity
{
    /** Первое слово — бренд (NEW BALANCE — двусоставный), остальное — модель. */
    public static function splitBrandModel(string $name): array
    {
        $multiWordBrands = ['NEW BALANCE'];
        $upper = mb_strtoupper($name);
        foreach ($multiWordBrands as $mb) {
            if (str_starts_with($upper, $mb.' ')) {
                return [$mb, trim(mb_substr($name, mb_strlen($mb) + 1))];
            }
        }
        $parts = explode(' ', $name, 2);

        return [mb_strtoupper($parts[0] ?? ''), trim($parts[1] ?? '')];
    }

    /**
     * Нормализация артикула: пробелы → дефис; Nike-коды (XX9999999 / XX9999 999)
     * приводятся к каноничному виду XX9999-999, чтобы расцветка не двоилась.
     */
    public static function normalizeArticle(string $article): string
    {
        $a = preg_replace('/\s+/u', '-', trim($article));
        if (preg_match('/^([A-Za-z]{2}\d{4})-?(\d{3})$/', $a, $m)) {
            return strtoupper($m[1]).'-'.$m[2];
        }

        return $a;
    }

    /**
     * Артикул = идентичность товара: все строки с одним артикулом должны попасть
     * в один и тот же (бренд+модель), даже если модель написана по-разному.
     * Сперва ищем уже существующий WarehouseProduct с этим артикулом (из прошлых
     * импортов/закупок); если не найден — первое вхождение в текущем батче задаёт
     * каноничные бренд+модель (модель = "{исходная модель} {артикул}").
     *
     * @param  array<int, array{brand:string,model:string,article:string}>  $rows
     * @return array<string, array{brand:string,model:string}> ключ — артикул
     */
    public static function canonicalizeByArticle(int $accountId, array $rows): array
    {
        $canonical = [];
        $articles = array_values(array_filter(array_unique(array_map(fn ($r) => $r['article'], $rows))));
        if (! empty($articles)) {
            foreach (WarehouseProduct::where('account_id', $accountId)->whereIn('article', $articles)->get() as $p) {
                $canonical[$p->article] = ['brand' => $p->brand, 'model' => $p->model];
            }
        }
        foreach ($rows as $r) {
            $art = $r['article'];
            if ($art === '' || isset($canonical[$art])) {
                continue;
            }
            $canonical[$art] = ['brand' => $r['brand'], 'model' => trim($r['model'].' '.$art)];
        }

        return $canonical;
    }

    /** Применить карту канонизации к одной строке — итоговые (brand, model). */
    public static function resolve(array $canonical, array $row): array
    {
        $art = $row['article'];
        if ($art !== '' && isset($canonical[$art])) {
            return [$canonical[$art]['brand'], $canonical[$art]['model']];
        }

        return [$row['brand'], $row['model']];
    }

    /**
     * Гарантировать карточку WarehouseProduct для (brand, model) и, если артикул
     * указан и ещё не занят другим товаром, закрепить его за этой карточкой —
     * чтобы БУДУЩИЕ импорты (любым путём: склад или закупки) распознали ту же
     * расцветку через canonicalizeByArticle(), а не завели вторую карточку.
     */
    public static function ensureProduct(int $accountId, string $brand, string $model, string $article): WarehouseProduct
    {
        $product = WarehouseProduct::firstOrCreate(
            ['account_id' => $accountId, 'brand' => $brand, 'model' => $model], []
        );
        if ($article !== '' && $product->article !== $article) {
            $taken = WarehouseProduct::where('account_id', $accountId)
                ->where('article', $article)->where('id', '!=', $product->id)->exists();
            if (! $taken) {
                $product->article = $article;
                $product->save();
            }
        }

        return $product;
    }
}
