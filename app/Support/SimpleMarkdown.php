<?php

namespace App\Support;

/**
 * Минимальный конвертер Markdown → HTML: заголовки (# ##), списки, ссылки,
 * жирный/курсив, инлайн-код, блоки кода, цитаты, hr. Без зависимостей.
 * Ровно того, что нужно для нашего USER_GUIDE.md.
 */
class SimpleMarkdown
{
    public static function toHtml(string $md): string
    {
        // Нормализуем переносы строк
        $md = str_replace(["\r\n", "\r"], "\n", $md);

        $lines = explode("\n", $md);
        $out = [];
        $inCode = false;
        $listType = null; // ul / ol / null
        $paraBuf = [];

        $flushPara = function () use (&$paraBuf, &$out) {
            if (empty($paraBuf)) return;
            $text = implode("\n", $paraBuf);
            $paraBuf = [];
            $out[] = '<p>'.self::inline($text).'</p>';
        };
        $closeList = function () use (&$listType, &$out) {
            if ($listType !== null) {
                $out[] = '</'.$listType.'>';
                $listType = null;
            }
        };

        foreach ($lines as $raw) {
            $line = rtrim($raw, ' ');

            // Тройной ` ``` ` — блок кода
            if (preg_match('/^```/', $line)) {
                $flushPara();
                $closeList();
                if ($inCode) {
                    $out[] = '</code></pre>';
                    $inCode = false;
                } else {
                    $out[] = '<pre><code>';
                    $inCode = true;
                }
                continue;
            }
            if ($inCode) {
                $out[] = htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }

            // Пустая строка
            if ($line === '') {
                $flushPara();
                $closeList();
                continue;
            }

            // Заголовки #..######
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                $flushPara();
                $closeList();
                $level = strlen($m[1]);
                $id = self::slugify($m[2]);
                $out[] = '<h'.$level.' id="'.$id.'">'.self::inline($m[2]).'</h'.$level.'>';
                continue;
            }

            // hr: --- или ___ или ***
            if (preg_match('/^(-{3,}|_{3,}|\*{3,})\s*$/', $line)) {
                $flushPara();
                $closeList();
                $out[] = '<hr>';
                continue;
            }

            // Blockquote (только сплошные "> …" превращаем в один блок)
            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $flushPara();
                $closeList();
                $out[] = '<blockquote>'.self::inline($m[1]).'</blockquote>';
                continue;
            }

            // Нумерованный список: "1. …"
            if (preg_match('/^\s*\d+\.\s+(.+)$/', $line, $m)) {
                $flushPara();
                if ($listType !== 'ol') {
                    $closeList();
                    $listType = 'ol';
                    $out[] = '<ol>';
                }
                $out[] = '<li>'.self::inline($m[1]).'</li>';
                continue;
            }

            // Ненумерованный список: "- …" или "* …"
            if (preg_match('/^\s*[-*]\s+(.+)$/', $line, $m)) {
                $flushPara();
                if ($listType !== 'ul') {
                    $closeList();
                    $listType = 'ul';
                    $out[] = '<ul>';
                }
                $out[] = '<li>'.self::inline($m[1]).'</li>';
                continue;
            }

            $paraBuf[] = $line;
        }
        $flushPara();
        $closeList();
        if ($inCode) $out[] = '</code></pre>';

        return implode("\n", $out);
    }

    private static function inline(string $text): string
    {
        // Экранируем сначала HTML
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Ссылки [text](url) — до кода/жирного, чтобы не мешать
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
            $href = $m[2];
            $label = $m[1];
            // Внутренние якорные ссылки (#anchor) — оставляем как есть.
            return '<a href="'.$href.'"'.(str_starts_with($href, 'http') ? ' target="_blank" rel="noopener"' : '').'>'.$label.'</a>';
        }, $text);

        // Инлайн-код `…`
        $text = preg_replace_callback('/`([^`]+)`/', fn ($m) => '<code>'.$m[1].'</code>', $text);

        // Жирный **…**
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

        // Курсив _…_ или *…* (осторожно — только не пересекается с **)
        $text = preg_replace('/(?<![*_])_(.+?)_(?![*_])/s', '<em>$1</em>', $text);

        return $text;
    }

    private static function slugify(string $text): string
    {
        // Убираем HTML и inline-разметку из заголовков
        $t = preg_replace('/`[^`]+`|\*\*|_/', '', $text);
        $t = mb_strtolower($t, 'UTF-8');
        $t = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $t);
        $t = preg_replace('/\s+/', '-', trim($t));
        return $t ?: 'section';
    }
}
