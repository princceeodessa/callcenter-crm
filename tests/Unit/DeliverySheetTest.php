<?php

namespace Tests\Unit;

use App\Support\Warehouse\DeliverySheet;
use PHPUnit\Framework\TestCase;

class DeliverySheetTest extends TestCase
{
    /** Таблица поставщика: заголовок не в первой строке, 11 колонок, свой порядок. */
    public function test_it_reads_a_supplier_sheet_by_header_names(): void
    {
        $parsed = DeliverySheet::parse($this->fixture('supplier-delivery-sample.xlsx'));
        $rows = $parsed['rows'];

        // 5 товарных строк; пустая строка и строка итогов не считаются позициями.
        $this->assertCount(5, $rows);
        $this->assertSame(1, $parsed['skipped'], 'строка итогов должна быть пропущена, а не импортирована');

        $this->assertSame('PUMA', $rows[0]['brand']);
        $this->assertSame('SUEDE XL', $rows[0]['model']);
        $this->assertSame('PX1111-01', $rows[0]['article']);
        $this->assertSame(1, $rows[0]['qty']);
    }

    /**
     * Себестоимость берётся из «Цена с НДС», а не из «Цена, юань» (699)
     * и не из «Закупочная цена, руб.» (9052) — иначе вся маржа поедет.
     */
    public function test_it_picks_the_landed_cost_column_not_yuan_or_bare_purchase_price(): void
    {
        $rows = DeliverySheet::parse($this->fixture('supplier-delivery-sample.xlsx'))['rows'];

        $this->assertSame(11000.0, $rows[0]['cost']);
        $this->assertSame(20000.0, $rows[4]['cost'], 'при кол-ве 2 цена за пару остаётся ценой за пару');
    }

    /** «42 1/2» и 42.5 — один размер; «43 1/3» — отдельный от «43». */
    public function test_it_normalizes_sizes_so_one_physical_size_is_one_sku(): void
    {
        $sizes = array_column(DeliverySheet::parse($this->fixture('supplier-delivery-sample.xlsx'))['rows'], 'size');

        $this->assertSame(['42', '42.5', '43.33', '42.5', '44'], $sizes);
        $this->assertSame('44.5', DeliverySheet::normalizeSize('44 1/2'));
        $this->assertSame('44.5', DeliverySheet::normalizeSize(44.5));
        $this->assertSame('44.5', DeliverySheet::normalizeSize('44,5'));
        $this->assertSame('42.67', DeliverySheet::normalizeSize('42 2/3'));
        $this->assertSame('36.5', DeliverySheet::normalizeSize('36.5 '));
        $this->assertSame('44', DeliverySheet::normalizeSize(44));
        $this->assertNull(DeliverySheet::normalizeSize('—'));
    }

    /** Прежний формат «Название | Размер | Кол-во | Артикул | Сумма» должен читаться как раньше. */
    public function test_it_still_reads_the_legacy_five_column_sheet(): void
    {
        $rows = DeliverySheet::parse($this->fixture('purchase-import-sample.xlsx'))['rows'];

        $this->assertCount(3, $rows);
        $qtyTwo = array_values(array_filter($rows, fn ($r) => $r['qty'] === 2));
        $this->assertCount(1, $qtyTwo);
        $this->assertSame(6000.0, $qtyTwo[0]['cost'], 'сумма строки делится на количество');
    }

    /**
     * Поставщик пишет название то с брендом, то сразу с модели. Наивное
     * «первое слово = бренд» заводило на складе бренды «AIR» и «METCON».
     *
     * @dataProvider brandNames
     */
    public function test_it_recovers_the_brand_from_the_model_line(string $name, string $brand, string $model): void
    {
        $this->assertSame([$brand, $model], DeliverySheet::resolveBrandModel($name));
    }

    /** @return array<string, array{0:string,1:string,2:string}> */
    public static function brandNames(): array
    {
        return [
            'модель без бренда' => ['air max DN Essential Black', 'NIKE', 'air max DN Essential Black'],
            'модельная линейка' => ['metcon 9 AMP Black-Bronzine', 'NIKE', 'metcon 9 AMP Black-Bronzine'],
            'джордан' => ['air Jordan Low Triple Black', 'JORDAN', 'air Jordan Low Triple Black'],
            'опечатка бренда' => ['SOLOMON Ultra Glide 4', 'SALOMON', 'Ultra Glide 4'],
            'женский префикс с опечаткой' => ['VMNS Air Max DN Dawn', 'NIKE', 'WMNS Air Max DN Dawn'],
            'бренд на месте' => ['Nike Dunk Low GS Triple Pink', 'NIKE', 'Dunk Low GS Triple Pink'],
            'двусоставный бренд' => ['New Balance 9060', 'NEW BALANCE', '9060'],
            'незнакомый бренд не трогаем' => ['HOKA Bondi 8', 'HOKA', 'Bondi 8'],
        ];
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__).'/Fixtures/'.$name;
    }
}
