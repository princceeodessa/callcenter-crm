<?php

namespace App\Services\Chat;

use App\Models\Message;

/**
 * Сообщения без текста — фото, голосовые, видео, файлы, ссылки, объявления, геолокация, стикеры, пересланные (04.11.2026):
 * раньше в «Чатах» они были пустым пузырём. Текст и вложения берутся из сохранённого сообщения площадки
 * (payload.message у Авито и истории ВК, payload.object.message у вебхука ВК) — без запросов к API.
 */
class MessageContent
{
    /** Пустое сообщение дописывается из payload (Message::saving); заполненное не трогаем. */
    public static function fill(Message $message): void
    {
        $payload = is_array($message->payload) ? $message->payload : [];
        if (trim((string) $message->body) !== '' || ! empty($payload['media'])) {
            return;
        }

        $vk = data_get($payload, 'object.message');
        $msg = is_array($vk) ? $vk : (is_array($payload['message'] ?? null) ? $payload['message'] : null);
        if ($msg === null) {
            return;
        }
        $isVk = is_array($vk) || array_key_exists('conversation_message_id', $msg) || array_key_exists('peer_id', $msg);
        ['body' => $body, 'media' => $media] = $isVk ? self::vk($msg) : self::avito($msg);

        if ($media !== []) {
            $payload['media'] = $media;
            $message->payload = $payload;
        }
        if ($body !== '') {
            $message->body = $body;
        }
    }

    /** @return array{body: string, media: list<array<string, string>>} */
    public static function avito(array $msg): array
    {
        $c = is_array($msg['content'] ?? null) ? $msg['content'] : [];
        $type = (string) ($msg['type'] ?? '');
        $text = trim((string) ($c['text'] ?? ''));
        $inApp = ' — открыть в приложении Авито';

        switch ($type) {
            case 'text':
            case 'system':
            case '':
                return ['body' => $text, 'media' => []];
            case 'image':
                $url = self::largest((array) data_get($c, 'image.sizes', []));
                return $url !== null
                    ? ['body' => '', 'media' => [['type' => 'image', 'url' => $url]]]
                    : ['body' => '📷 Фото'.$inApp, 'media' => []];
            case 'link':
                $url = trim((string) data_get($c, 'link.url', ''));
                $label = trim((string) data_get($c, 'link.text', ''));
                return ['body' => $label !== '' && $label !== $url ? $label."\n".$url : ($url ?: '🔗 Ссылка'.$inApp), 'media' => []];
            case 'item':
                $title = trim((string) data_get($c, 'item.title', ''));
                $price = trim((string) data_get($c, 'item.price_string', ''));
                $url = trim((string) data_get($c, 'item.item_url', ''));
                return ['body' => '🏷 Объявление: '.($title ?: 'без названия').($price !== '' ? ' · '.$price : '').($url !== '' ? "\n".$url : ''), 'media' => []];
            case 'location':
                $place = trim((string) (data_get($c, 'location.text') ?: data_get($c, 'location.title') ?: 'Геолокация'));
                $lat = data_get($c, 'location.lat');
                $lon = data_get($c, 'location.lon');
                return ['body' => '📍 '.$place.(is_numeric($lat) && is_numeric($lon) ? "\nhttps://yandex.ru/maps/?pt={$lon},{$lat}&z=16&l=map" : ''), 'media' => []];
            case 'voice':
                return ['body' => '🎤 Голосовое сообщение'.$inApp, 'media' => []];
            case 'video':
                return ['body' => '🎬 Видео'.$inApp, 'media' => []];
            case 'file':
                $name = trim((string) data_get($c, 'file.name', ''));
                return ['body' => '📎 Файл'.($name !== '' ? ' «'.$name.'»' : '').$inApp, 'media' => []];
            case 'appCall':
            case 'call':
                return ['body' => '📞 Звонок через Авито', 'media' => []];
            case 'deleted':
                return ['body' => 'Сообщение удалено', 'media' => []];
            default:
                return ['body' => $text !== '' ? $text : 'Сообщение ('.$type.')'.$inApp, 'media' => []];
        }
    }

    /** @return array{body: string, media: list<array<string, string>>} */
    public static function vk(array $msg, int $depth = 0): array
    {
        $lines = [];
        $media = [];
        foreach ((array) ($msg['attachments'] ?? []) as $a) {
            $type = (string) ($a['type'] ?? '');
            $x = is_array($a[$type] ?? null) ? $a[$type] : [];
            switch ($type) {
                case 'photo':
                    $url = self::largestVk((array) ($x['sizes'] ?? []));
                    self::add($media, $lines, $url === null ? null : ['type' => 'photo', 'url' => $url], '📷 Фото');
                    break;
                case 'sticker':
                    $url = self::largestVk((array) ($x['images'] ?? []));
                    self::add($media, $lines, $url === null ? null : ['type' => 'image', 'url' => $url], '🙂 Стикер');
                    break;
                case 'graffiti':
                    self::add($media, $lines, empty($x['url']) ? null : ['type' => 'image', 'url' => (string) $x['url']], '🎨 Граффити');
                    break;
                case 'doc':
                    self::add($media, $lines, empty($x['url']) ? null : ['type' => 'document', 'url' => (string) $x['url'], 'file_name' => (string) ($x['title'] ?? 'Файл')], '📎 Файл');
                    break;
                case 'audio_message':
                    $url = (string) ($x['link_mp3'] ?? $x['link_ogg'] ?? '');
                    self::add($media, $lines, $url === '' ? null : ['type' => 'link', 'url' => $url, 'file_name' => '🎤 Голосовое сообщение — прослушать'], '🎤 Голосовое сообщение');
                    break;
                case 'video':
                    $title = trim((string) ($x['title'] ?? ''));
                    $url = isset($x['owner_id'], $x['id']) ? 'https://vk.com/video'.$x['owner_id'].'_'.$x['id'] : '';
                    self::add($media, $lines, $url === '' ? null : ['type' => 'link', 'url' => $url, 'file_name' => '🎬 Видео'.($title !== '' ? ': '.$title : '')], '🎬 Видео');
                    break;
                case 'link':
                    $lines[] = trim('🔗 '.trim((string) ($x['title'] ?? ''))."\n".(string) ($x['url'] ?? ''));
                    break;
                case 'audio':
                    $lines[] = '🎵 '.trim(trim((string) ($x['artist'] ?? '')).' — '.trim((string) ($x['title'] ?? '')), ' —');
                    break;
                case 'wall':
                    $t = trim((string) ($x['text'] ?? ''));
                    $lines[] = '📰 Запись со стены'.($t !== '' ? ': '.mb_substr($t, 0, 300) : '');
                    break;
                case 'story':
                    $lines[] = '📖 История';
                    break;
                case 'market':
                    $lines[] = '🛒 Товар: '.trim((string) ($x['title'] ?? ''));
                    break;
                case 'poll':
                    $lines[] = '📊 Опрос: '.trim((string) ($x['question'] ?? ''));
                    break;
                case 'gift':
                    $lines[] = '🎁 Подарок';
                    break;
                default:
                    $lines[] = 'Вложение ('.$type.')';
            }
        }
        if ($depth === 0 && ! empty($msg['fwd_messages'])) {
            $fwd = [];
            foreach ((array) $msg['fwd_messages'] as $f) {
                if (! is_array($f)) {
                    continue;
                }
                $inner = self::vk($f, 1);
                $t = trim((string) ($f['text'] ?? ''));
                $part = $t !== '' ? $t : $inner['body'];
                $media = array_merge($media, $inner['media']);
                if ($part !== '') {
                    $fwd[] = '— '.$part;
                }
            }
            $lines[] = '↪ Пересланное'.($fwd ? ":\n".implode("\n", $fwd) : '');
        }

        $text = trim((string) ($msg['text'] ?? ''));

        return ['body' => $depth === 0 && $text !== '' ? $text : trim(implode("\n", $lines)), 'media' => $media];
    }

    /** Вложение — в media, а если ссылки нет — строкой в текст. */
    private static function add(array &$media, array &$lines, ?array $item, string $line): void
    {
        if ($item !== null) {
            $media[] = $item;
        } else {
            $lines[] = $line;
        }
    }

    /** Самый большой вариант картинки Авито: {"1280x960": url, …} */
    private static function largest(array $sizes): ?string
    {
        $best = null;
        $area = -1;
        foreach ($sizes as $size => $url) {
            [$w, $h] = array_map('intval', explode('x', (string) $size) + [0, 0]);
            if (is_string($url) && $url !== '' && $w * $h > $area) {
                [$best, $area] = [$url, $w * $h];
            }
        }

        return $best;
    }

    /** Самый большой вариант картинки ВК: [{url, width, height}, …] */
    private static function largestVk(array $sizes): ?string
    {
        usort($sizes, fn ($a, $b) => ((int) ($b['width'] ?? 0) * (int) ($b['height'] ?? 0)) <=> ((int) ($a['width'] ?? 0) * (int) ($a['height'] ?? 0)));
        $url = $sizes[0]['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
