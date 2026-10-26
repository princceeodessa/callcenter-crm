<?php

namespace App\Support\Ui;

use App\Models\Deal;
use App\Models\User;

/**
 * Редизайн колл-центра «Nordika» (архив дизайнера 26.10.2026, public/css/nordika-crm.css): что нужно разметке —
 * чип источника лида, цвет точки этапа, кружок инициалов, телефон в виде «+7 919 909-85-55», иконки меню.
 * Только представление: источник берётся из того, что сделка уже знает (lead_source_surface_class, счётчики канбана).
 */
class Nordika
{
    private const PHONE_SVG = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="#fff" stroke-width="2.4"><path d="M5 4h3l1.5 5-2 1.5a12 12 0 006 6l1.5-2 5 1.5V20a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/></svg>';

    /** Источники как у дизайнера: подпись, цвет (переменная темы), значок. */
    public const SOURCES = [
        'avito' => ['Avito', 'var(--av)', 'A'],
        'vk' => ['VK', 'var(--vk)', '<svg viewBox="0 0 24 24" width="11" height="11" fill="#fff"><path d="M12.8 16.3c-5 0-8.2-3.6-8.3-9.5h2.6c.1 4.4 2.1 6.2 3.6 6.6V6.8h2.4v3.8c1.5-.2 3-1.9 3.6-3.8h2.4c-.4 2.3-2 4-3.2 4.7 1.2.6 3 2.1 3.7 4.8h-2.7c-.5-1.7-1.9-3-3.8-3.2v3.2z"/></svg>'],
        'call' => ['Звонок', 'var(--call)', self::PHONE_SVG],
        'lemana' => ['Лемана', 'var(--lemana)', 'Л'],
        'site' => ['Сайт', 'var(--site)', '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="#fff" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 000 18 14 14 0 000-18"/></svg>'],
        'tg' => ['Telegram', 'var(--tg)', '<svg viewBox="0 0 24 24" width="11" height="11" fill="#fff"><path d="M21 4L3 11l5 2 2 6 3-4 5 4z"/></svg>'],
        'bitrix' => ['Битрикс', '#2f6fdf', 'Б'],
        'crm' => ['CRM', 'var(--muted)', '·'],
    ];

    /** Палитра кружков сотрудников и точек этапов (как в прототипе). */
    private const PALETTE = ['#1fae55', '#3f72ff', '#8a5cf6', '#f0a526', '#17b3c3', '#e5484d', '#ff7a2f', '#2aa3e0'];

    /** Новый каркас — у сотрудников потолков (колл-центр, замерщики, документы, проектировка); у кроссовок прежний. */
    public static function enabled(): bool
    {
        $user = auth()->user();

        return $user !== null && ! in_array($user->role, ['sneaker_head', 'sneaker_operator', 'sneaker_owner'], true);
    }

    /**
     * Боковое меню по ролям — те же пункты, что видела роль в прежней верхней панели.
     *
     * @return array<string, list<array{0: string, 1: string, 2: string, 3: string}>> группа => [маршрут, подпись, иконка, активен для]
     */
    public static function nav(User $user): array
    {
        $role = $user->role;
        $desk = in_array($role, ['admin', 'main_operator', 'operator'], true);
        $docs = $role === 'documents_operator';

        $groups = [
            'Сделки' => [
                $desk ? ['deals.kanban', 'Канбан', 'kanban', 'deals.kanban'] : null,
                $desk ? ['deals.index', 'Список', 'list', 'deals.index'] : null,
                $desk || $docs ? ['tasks.index', 'Дела', 'tasks', 'tasks.*'] : null,
                $desk ? ['deals.closed', 'Завершённые', 'done', 'deals.closed'] : null,
            ],
            'Работа' => [
                $desk ? ['chats.index', 'Чаты', 'chats', 'chats.*'] : null,
                $desk || $role === 'measurer' ? ['calendar.index', 'Календарь', 'calendar', 'calendar.*'] : null,
                $desk || $docs ? ['nonclosures.index', 'Документы', 'docs', 'nonclosures.*'] : null,
                in_array($role, ['admin', 'constructor'], true) ? ['ceiling-projects.index', 'Проектировка', 'project', 'ceiling-projects.*'] : null,
            ],
            'Система' => [
                $role === 'admin' ? ['settings.integrations.index', 'Интеграции', 'integrations', 'settings.integrations.*'] : null,
                $role === 'admin' ? ['settings.imports.bitrix.index', 'Импорт Bitrix', 'import', 'settings.imports.*'] : null,
                in_array($role, ['admin', 'main_operator'], true) ? ['settings.users.index', 'Пользователи', 'users', 'settings.users.*'] : null,
                $desk || $role === 'measurer' ? ['reports.monthly', 'Отчёты', 'reports', 'reports.*'] : null,
            ],
        ];

        return array_filter(array_map(fn ($items) => array_values(array_filter($items)), $groups));
    }

    /** Заголовок верхней панели по странице: [заголовок, приписка]; страница может задать свой (@section('nd_title')). */
    public static function title(): array
    {
        foreach ([
            'deals.kanban' => ['Сделки', '— канбан'],
            'deals.index' => ['Сделки', '— список'],
            'deals.closed' => ['Сделки', '— завершённые'],
            'deals.create' => ['Новая сделка', ''],
            'deals.*' => ['Сделка', ''],
            'tasks.*' => ['Дела', ''],
            'chats.*' => ['Чаты', ''],
            'calendar.*' => ['Календарь', '— замеры'],
            'nonclosures.*' => ['Документы', ''],
            'ceiling-projects.*' => ['Проектировка', ''],
            'settings.integrations.*' => ['Интеграции', ''],
            'settings.imports.*' => ['Импорт Bitrix', ''],
            'settings.users.*' => ['Пользователи', ''],
            'settings.*' => ['Настройки', ''],
            'reports.*' => ['Отчёты', ''],
            'notifications.*' => ['Уведомления', ''],
        ] as $pattern => $title) {
            if (request()->routeIs($pattern)) {
                return $title;
            }
        }

        return [(string) config('app.name'), ''];
    }

    public static function roleLabel(?string $role): string
    {
        return [
            'admin' => 'Администратор',
            'main_operator' => 'Руководитель',
            'operator' => 'Оператор',
            'documents_operator' => 'Документы',
            'measurer' => 'Замерщик',
            'constructor' => 'Проектировщик',
        ][$role] ?? 'Сотрудник';
    }

    /** «Виктория Соковикова» → «Виктория С.» */
    public static function shortName(?string $name): string
    {
        $parts = preg_split('/\s+/u', trim((string) $name)) ?: [];

        return trim(($parts[0] ?? '').(isset($parts[1]) ? ' '.mb_substr($parts[1], 0, 1).'.' : ''));
    }

    /** Ключ источника сделки: Лемана ПРО (заказы через hands.ru) — по счётчику канбана, остальное — по lead_source_surface_class. */
    public static function sourceKey(Deal $deal): string
    {
        $attributes = $deal->getAttributes();
        if (array_key_exists('lemana_lead_form_activities_count', $attributes)) {
            if ((int) $attributes['lemana_lead_form_activities_count'] > 0) {
                return 'lemana';
            }
        } elseif ($deal->relationLoaded('activities')
            && $deal->activities->contains(fn ($a) => $a->type === 'lead_form' && data_get($a->payload, 'provider') === 'hands')) {
            return 'lemana';   // карточка сделки: лента уже загружена
        }

        // класс вида «source-surface source-surface-vk» (канал переписки или запасной источник сделки)
        preg_match('/source-surface-([a-z_]+)/', (string) $deal->lead_source_surface_class, $m);

        return match ($m[1] ?? 'default') {
            'avito' => 'avito',
            'vk' => 'vk',
            'telegram' => 'tg',
            'tilda' => 'site',
            'megafon_vats' => 'call',
            'bitrix' => 'bitrix',
            default => 'crm',
        };
    }

    /** @return array{0: string, 1: string, 2: string} [подпись, цвет, значок] */
    public static function source(string $key): array
    {
        return self::SOURCES[$key] ?? self::SOURCES['crm'];
    }

    /** Чип источника: <span class="nd-src">… */
    public static function sourceChip(string $key): string
    {
        [$label, $color, $icon] = self::source($key);

        return '<span class="nd-src" style="--src:'.$color.'"><span class="nd-ic">'.$icon.'</span>'.e($label).'</span>';
    }

    /** Точка этапа: по названию (как в прототипе), иначе по порядку. */
    public static function stageDot(string $name, int $index): string
    {
        $n = mb_strtolower($name);

        return match (true) {
            str_contains($n, 'поступил') => '#3f72ff',
            str_contains($n, 'взяли') || str_contains($n, 'связыва') => '#17b3c3',
            str_contains($n, 'квалиф') || str_contains($n, 'дожать') => '#f0a526',
            str_contains($n, 'замер') => '#1fae55',
            str_contains($n, 'нецелев') => '#e5484d',
            str_contains($n, 'заверш') || str_contains($n, 'договор') => '#8a5cf6',
            default => self::PALETTE[$index % count(self::PALETTE)],
        };
    }

    /** Кружок инициалов: цвет от имени, шрифт ≈ 0,42 диаметра (иначе инициалы вылезают — §7 инструкции). */
    public static function avatar(?string $name, int $size = 18): string
    {
        $name = trim((string) $name);
        // инициалы — только из букв: «Всем (колл-центр)» → «ВК», а не «В(»
        $parts = preg_split('/\s+/u', trim((string) preg_replace('/[^\p{L}\s]+/u', ' ', $name))) ?: [];
        $ini = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
        $hash = 0;
        foreach (mb_str_split($name) as $ch) {
            $hash = ($hash * 31 + mb_ord($ch)) & 0xFFFFFFF;
        }
        $color = $name === '' ? '#7b8494' : self::PALETTE[$hash % count(self::PALETTE)];
        $fs = (int) round($size * 0.42);

        return '<span class="nd-mini-av" style="background:'.$color.';width:'.$size.'px;height:'.$size.'px;font-size:'.$fs.'px">'.e($ini !== '' ? $ini : '—').'</span>';
    }

    /** Первое слово имени: «Наталья Петрова» → «Наталья». */
    public static function firstName(?string $name): string
    {
        return (string) (preg_split('/\s+/u', trim((string) $name))[0] ?? '');
    }

    /** «79199098555» → «+7 919 909-85-55»; не российский номер — как есть. */
    public static function phone(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if ($d === '') {
            return null;
        }
        if (strlen($d) === 11 && in_array($d[0], ['7', '8'], true)) {
            return '+7 '.substr($d, 1, 3).' '.substr($d, 4, 3).'-'.substr($d, 7, 2).'-'.substr($d, 9, 2);
        }

        return (string) $phone;
    }

    /** Иконки меню (пути SVG из прототипа). */
    public const ICONS = [
        'kanban' => '<rect x="3" y="4" width="5" height="16" rx="1.5"/><rect x="10" y="4" width="5" height="11" rx="1.5"/><rect x="17" y="4" width="4" height="8" rx="1.5"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'tasks' => '<path d="M9 11l3 3 8-8M4 12v6a2 2 0 002 2h12"/>',
        'done' => '<path d="M20 6L9 17l-5-5"/>',
        'chats' => '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'docs' => '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5"/>',
        'project' => '<path d="M3 21v-5l11-11 5 5L8 21z"/><path d="M12 7l5 5"/>',
        'integrations' => '<path d="M12 2l3 5 5 .5-3.5 3.5 1 5L12 18l-5.5 3 1-5L4 12.5 9 12z"/>',
        'import' => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20v-1a5 5 0 017-4.5M16 7l5 5M16 12l5-5"/>',
        'reports' => '<path d="M4 19V5M4 19h16M8 15v-4M12 15V8M16 15v-6"/>',
        'logout' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
    ];
}
