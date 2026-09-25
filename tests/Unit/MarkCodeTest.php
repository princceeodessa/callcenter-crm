<?php

namespace Tests\Unit;

use App\Support\Marking\MarkCode;
use PHPUnit\Framework\TestCase;

class MarkCodeTest extends TestCase
{
    private const GS = "\x1D";

    private const GTIN = '04601234567890';

    private const SERIAL = 'Ab/9?,."%&\'_Z';          // 13 символов, есть «неудобные» знаки

    private const KEY = 'EE10';

    private const CRYPTO = 'dGVzdGNyeXB0b3RhaWx0ZXN0Y3J5cHRvdGFpbHRlc3Q=';   // 44 символа

    private function full(): string
    {
        return '01'.self::GTIN.'21'.self::SERIAL.self::GS.'91'.self::KEY.self::GS.'92'.self::CRYPTO;
    }

    public function test_a_correct_code_is_left_as_is(): void
    {
        $this->assertSame($this->full(), MarkCode::normalize($this->full()));
    }

    /** Сканер в режиме клавиатуры теряет GS — для обуви длины полей известны, возвращаем. */
    public function test_lost_group_separators_are_restored_for_footwear_codes(): void
    {
        $scanned = str_replace(self::GS, '', $this->full());

        $this->assertSame($this->full(), MarkCode::normalize($scanned));
    }

    public function test_textual_separators_become_real_gs(): void
    {
        $typed = str_replace(self::GS, '<GS>', $this->full());

        $this->assertSame($this->full(), MarkCode::normalize('  '.$typed."\r\n"));
    }

    /** Сканер «печатал» в русской раскладке: «Фи.9,бюЭ…» вместо «Ab/9?,."…». */
    public function test_russian_keyboard_layout_is_converted_back(): void
    {
        $ruSerial = 'Фи.9,бюЭ%?э_Я';
        $ruKey = 'УУ10';
        $ruCrypto = strtr(self::CRYPTO, [
            'd' => 'в', 'G' => 'П', 'V' => 'М', 'z' => 'я', 'Z' => 'Я', 'N' => 'Т', 'j' => 'о', 'c' => 'с',
            'n' => 'т', 'l' => 'д', 'B' => 'И', 'b' => 'и', '0' => '0', 'R' => 'К', 'h' => 'р', 'W' => 'Ц',
            'x' => 'ч', 'Y' => 'Н', 'X' => 'Ч', 'J' => 'О', 'y' => 'н', 'H' => 'Р', 'Q' => 'Й', 'L' => 'Д',
            'S' => 'Ы', 'a' => 'ф', 'C' => 'С', 'e' => 'у', 'T' => 'Е', 'E' => 'У', 'F' => 'А', 'D' => 'В',
        ]);
        $scanned = '01'.self::GTIN.'21'.$ruSerial.'91'.$ruKey.'92'.$ruCrypto;

        $this->assertSame($this->full(), MarkCode::normalize($scanned));
    }

    public function test_scanner_prefix_and_leading_fnc1_are_dropped(): void
    {
        $this->assertSame($this->full(), MarkCode::normalize(']d2'.$this->full()));
        $this->assertSame($this->full(), MarkCode::normalize(self::GS.$this->full()));
    }

    public function test_unknown_layout_without_gs_is_not_guessed(): void
    {
        // Молочка: серийный 6 символов — не наш шаблон, не выдумываем разделители.
        $milk = '0104601234567890216AbCd193Ab1x';
        $this->assertSame($milk, MarkCode::normalize($milk));
    }

    public function test_it_recognizes_and_describes_codes(): void
    {
        $this->assertTrue(MarkCode::looksLike($this->full()));
        $this->assertFalse(MarkCode::looksLike('4601234567890'), 'EAN-13 — не код маркировки');
        $this->assertFalse(MarkCode::looksLike('HQ8605-002'));

        $this->assertSame('(01) '.self::GTIN.' (21) '.self::SERIAL, MarkCode::humanReadable($this->full()));
        $this->assertTrue(MarkCode::parse($this->full())['has_crypto']);
        $this->assertFalse(MarkCode::parse('01'.self::GTIN.'21'.self::SERIAL)['has_crypto']);
    }

    public function test_datamatrix_input_marks_gs1_and_escapes_caret(): void
    {
        $dm = MarkCode::forDataMatrix('01'.self::GTIN.'21AB^'.self::GS.'91'.self::KEY);

        $this->assertSame('^FNC101'.self::GTIN.'21AB^094^FNC191'.self::KEY, $dm);
    }
}
