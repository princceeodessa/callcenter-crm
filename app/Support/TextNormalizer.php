<?php

namespace App\Support;

class TextNormalizer
{
    public static function normalizeMojibake(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(str_replace("\u{00A0}", ' ', $value));

        if ($value === '') {
            return '';
        }

        $decodedWhole = self::decodeChunk($value);
        if ($decodedWhole !== $value) {
            return $decodedWhole;
        }

        $parts = preg_split('/(\s+)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $value;
        }

        foreach ($parts as $index => $part) {
            if ($part === '' || preg_match('/^\s+$/u', $part) === 1) {
                continue;
            }

            $parts[$index] = self::decodeChunk($part);
        }

        return trim(implode('', $parts));
    }

    private static function decodeChunk(string $value): string
    {
        $rawValue = $value;
        $value = trim(str_replace("\u{00A0}", ' ', $value));

        if ($value === '' || self::mojibakeScore($value) === 0) {
            return $value;
        }

        $converted = @mb_convert_encoding($rawValue, 'Windows-1251', 'UTF-8');
        if (!is_string($converted) || $converted === '' || preg_match('//u', $converted) !== 1) {
            return $value;
        }

        $converted = trim(str_replace("\u{00A0}", ' ', $converted));
        preg_match_all('/[\x{0410}-\x{044F}ЁёA-Za-z0-9]/u', $converted, $readableMatches);

        return self::mojibakeScore($converted) < self::mojibakeScore($value) && count($readableMatches[0]) > 0
            ? $converted
            : $value;
    }

    private static function mojibakeScore(string $value): int
    {
        preg_match_all('/(?:[РС][\x{0400}-\x{040F}\x{0450}-\x{045F}\x{00A0}-\x{00FF}\x{2010}-\x{203A}\x{20AC}\x{2116}\x{2122}]|[ÐÑ][\x{0080}-\x{00FF}])/u', $value, $matches);

        return count($matches[0]);
    }
}
