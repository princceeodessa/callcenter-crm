<?php

namespace App\Support\Marking;

/**
 * Код маркировки «Честного знака» (GS1 DataMatrix).
 *
 * Код обуви выглядит так:
 *   01 + GTIN(14) + 21 + серийный(13) + GS + 91 + ключ(4) + GS + 92 + крипто-подпись(44)
 * где GS — невидимый символ-разделитель (ASCII 29). Без него касса и
 * «Честный знак» код не примут, а сканер в режиме клавиатуры его обычно теряет.
 * Вторая частая беда — сканер «печатает» в русской раскладке: вместо «Ab/9»
 * приходит «Фи.9». Обе вещи здесь чинятся, пока это можно сделать однозначно.
 */
final class MarkCode
{
    public const GS = "\x1D";

    /**
     * Длины полей там, где их знаем, — только по ним можно вернуть потерянные GS.
     * Порядок — от самого длинного шаблона к короткому.
     *
     * @var array<int, string>
     */
    private const NO_GS_PATTERNS = [
        // обувь, одежда, лёгкая промышленность: серийный 13, ключ 91 (4), подпись 92 (44)
        '~^01(\d{14})21(.{13})91(.{4})92(.{44})$~s',
        // «короткий» криптохвост 93 (4) при серийном 13
        '~^01(\d{14})21(.{13})93(.{4})$~s',
    ];

    /** Раскладка ЙЦУКЕН → QWERTY: что напечатал бы сканер в английской раскладке. */
    private const RU_TO_EN = [
        'й' => 'q', 'ц' => 'w', 'у' => 'e', 'к' => 'r', 'е' => 't', 'н' => 'y', 'г' => 'u', 'ш' => 'i', 'щ' => 'o', 'з' => 'p',
        'х' => '[', 'ъ' => ']', 'ф' => 'a', 'ы' => 's', 'в' => 'd', 'а' => 'f', 'п' => 'g', 'р' => 'h', 'о' => 'j', 'л' => 'k',
        'д' => 'l', 'ж' => ';', 'э' => "'", 'я' => 'z', 'ч' => 'x', 'с' => 'c', 'м' => 'v', 'и' => 'b', 'т' => 'n', 'ь' => 'm',
        'б' => ',', 'ю' => '.', 'ё' => '`',
        'Й' => 'Q', 'Ц' => 'W', 'У' => 'E', 'К' => 'R', 'Е' => 'T', 'Н' => 'Y', 'Г' => 'U', 'Ш' => 'I', 'Щ' => 'O', 'З' => 'P',
        'Х' => '{', 'Ъ' => '}', 'Ф' => 'A', 'Ы' => 'S', 'В' => 'D', 'А' => 'F', 'П' => 'G', 'Р' => 'H', 'О' => 'J', 'Л' => 'K',
        'Д' => 'L', 'Ж' => ':', 'Э' => '"', 'Я' => 'Z', 'Ч' => 'X', 'С' => 'C', 'М' => 'V', 'И' => 'B', 'Т' => 'N', 'Ь' => 'M',
        'Б' => '<', 'Ю' => '>', 'Ё' => '~',
        // Символы, которые в русской раскладке стоят на других клавишах.
        '.' => '/', ',' => '?', '"' => '@', '№' => '#', ';' => '$', ':' => '^', '?' => '&', '/' => '|',
    ];

    /** Как разделитель GS приходит «текстом» от сканеров, файлов и ручного ввода. */
    private const GS_ALIASES = ['<GS>', '<gs>', '{GS}', '\\x1D', '\\x1d', '\\u001D', '\\u001d', '␝', '\\F', '~1'];

    /**
     * Привести код к виду, который примут касса и «Честный знак».
     * Если однозначно починить нельзя — вернёт строку без догадок (только обрезанные края).
     */
    public static function normalize(string $raw): string
    {
        $code = str_replace(["\r", "\n", "\t"], '', $raw);
        $code = trim($code, " \u{00A0}");

        // Префикс AIM «]d2»/«]C1», который добавляют некоторые сканеры, и ведущий FNC1/GS.
        $code = preg_replace('~^\](?:d2|C1|Q3|e0)~', '', $code) ?? $code;
        $code = str_replace(self::GS_ALIASES, self::GS, $code);
        $code = ltrim($code, self::GS);

        $code = self::fromRussianLayout($code);

        if (! str_contains($code, self::GS)) {
            foreach (self::NO_GS_PATTERNS as $pattern) {
                if (preg_match($pattern, $code, $m)) {
                    $code = '01'.$m[1].'21'.$m[2];
                    $code .= count($m) === 5
                        ? self::GS.'91'.$m[3].self::GS.'92'.$m[4]
                        : self::GS.'93'.$m[3];
                    break;
                }
            }
        }

        return $code;
    }

    /** Сканер «печатал» в русской раскладке («ША4229» вместо «IF4229») — вернуть латиницу. */
    public static function fromRussianLayout(string $text): string
    {
        return preg_match('~\p{Cyrillic}~u', $text) ? strtr($text, self::RU_TO_EN) : $text;
    }

    /** Похоже ли на код маркировки (а не на штрихкод EAN или артикул). */
    public static function looksLike(string $code): bool
    {
        return (bool) preg_match('~^01\d{14}21\S~', $code);
    }

    /** GTIN и серийный номер — для подписи под кодом и поиска. */
    public static function parse(string $code): ?array
    {
        if (! preg_match('~^01(\d{14})21([^\x1D]+)~', $code, $m)) {
            return null;
        }

        return [
            'gtin' => $m[1],
            'serial' => $m[2],
            'has_crypto' => str_contains($code, self::GS),
        ];
    }

    /** «(01) 04601234567890 (21) ABC…» — как печатают под DataMatrix. */
    public static function humanReadable(string $code): string
    {
        $p = self::parse($code);

        return $p ? '(01) '.$p['gtin'].' (21) '.$p['serial'] : $code;
    }

    /**
     * Строка для генератора DataMatrix (bwip-js, parsefnc): FNC1 в начале = режим GS1,
     * каждый GS → FNC1, а сам «^» экранируется, чтобы не стать командой.
     */
    public static function forDataMatrix(string $code): string
    {
        $escaped = str_replace('^', '^094', $code);

        return '^FNC1'.str_replace(self::GS, '^FNC1', $escaped);
    }
}
