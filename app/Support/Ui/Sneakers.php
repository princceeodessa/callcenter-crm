<?php

namespace App\Support\Ui;

use App\Models\Purchase;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Редизайн «Нордика» кроссовочного отдела (пакет дизайнера 28.10.2026): каркас layouts/sneakers — боковое меню по группам,
 * шапка с поиском, темой, звуком и колокольчиком. Страницы отдела переходят на него по одной; колл-центр не трогается
 * (у него свой каркас — App\Support\Ui\Nordika). Классы разметки — с приставкой ns- (public/css/nordika-sneakers.css).
 */
class Sneakers
{
    public const ROLES = ['sneaker_head', 'sneaker_operator', 'sneaker_owner'];

    /** Значки меню и кнопок — SVG из прототипа (объект I в sneakers-reference.js) */
    public const ICONS = [
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/>',
        'scan' => '<path d="M4 7V5a1 1 0 011-1h2M17 4h2a1 1 0 011 1v2M20 17v2a1 1 0 01-1 1h-2M7 20H5a1 1 0 01-1-1v-2"/><path d="M8 8v8M11 8v8M14 8v8M17 8v8"/>',
        'sell' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'cal' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'box' => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13L22 12v6a2 2 0 01-2 2H4a2 2 0 01-2-2v-6z"/>',
        'truck' => '<path d="M1 4h14v12H1zM15 9h4l4 4v3h-8"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="18.5" cy="18.5" r="2"/>',
        'chart' => '<path d="M4 19V5M4 19h16M8 15v-4M12 15V8M16 15v-6"/>',
        'kanban' => '<rect x="3" y="4" width="5" height="16" rx="1.5"/><rect x="10" y="4" width="5" height="11" rx="1.5"/><rect x="17" y="4" width="4" height="8" rx="1.5"/>',
        'tasks' => '<path d="M9 11l3 3 8-8M4 12v6a2 2 0 002 2h12"/>',
        'home' => '<path d="M3 11l9-8 9 8v9a1 1 0 01-1 1h-5v-6h-6v6H4a1 1 0 01-1-1z"/>',
        'report' => '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20v-1a5 5 0 017-4.5"/><circle cx="17" cy="10" r="2.5"/><path d="M14 20a4 4 0 018 0"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 015 .5c0 1.5-2.5 2-2.5 3.5M12 17h.01"/>',
        'bell' => '<path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>',
        'vol' => '<path d="M11 5L6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 010 7M18.5 5.5a9 9 0 010 13"/>',
        'mute' => '<path d="M11 5L6 9H3v6h3l5 4z"/><path d="M16 9.5l5 5M21 9.5l-5 5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L21 2M17 6l3 3"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'check' => '<path d="M5 12l5 5 9-11"/>',
        'chev' => '<path d="M6 9l6 6 6-6"/>',
        'logout' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>',
        'pc' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'warn' => '<path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'bulb' => '<path d="M9 18h6M10 22h4M12 2a7 7 0 00-4 12.7V17h8v-2.3A7 7 0 0012 2z"/>',
        'hand' => '<path d="M7 11l4-4 3 3 4-4M3 21l6-6 3 3 9-9"/>',
        'doc1c' => '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5"/>',
        'up' => '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
        'down' => '<path d="M12 4v12M7 11l5 5 5-5M4 20h16"/>',
        'tag' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 01-2.8 0L3 13V3h10l7.6 7.6a2 2 0 010 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'pen' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.6 1.6 0 00-1.8-.3 1.6 1.6 0 00-1 1.5V21a2 2 0 01-4 0v-.1a1.6 1.6 0 00-1-1.5 1.6 1.6 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.6 1.6 0 00.3-1.8 1.6 1.6 0 00-1.5-1H3a2 2 0 010-4h.1a1.6 1.6 0 001.5-1 1.6 1.6 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.6 1.6 0 001.8.3H9a1.6 1.6 0 001-1.5V3a2 2 0 014 0v.1a1.6 1.6 0 001 1.5 1.6 1.6 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.6 1.6 0 00-.3 1.8V9a1.6 1.6 0 001.5 1H21a2 2 0 010 4h-.1a1.6 1.6 0 00-1.5 1z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'cam' => '<path d="M3 8a2 2 0 012-2h2l2-2h6l2 2h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><circle cx="12" cy="13" r="3.5"/>',
    ];

    /** Подписи ролей сотрудников отдела (меню, «Пользователи») */
    public const ROLE_LABELS = [
        'sneaker_head' => 'Руководитель',
        'sneaker_operator' => 'Продавец',
        'sneaker_owner' => 'Владелец',
        'smm' => 'SMM-специалист',
    ];

    /** Цвета источников продаж (Deal::SNEAKER_SOURCES) — как SRC_C в прототипе */
    public const SOURCE_COLORS = [
        'Avito' => 'var(--av)', 'ВК' => 'var(--vk)', 'Instagram' => 'var(--ig)', 'Telegram' => 'var(--tg)', 'Сайт' => 'var(--site)',
        'Сарафан' => 'var(--lemana)', 'Магазин' => 'var(--accent)', 'Другое' => '#7b8494',
    ];

    /** Плашка источника клиента; пустой — «не указан» */
    public static function srcChip(?string $source): string
    {
        $source = trim((string) $source);
        $none = $source === '' || in_array(mb_strtolower($source), ['не указан', '—'], true);

        return '<span class="ns-src" style="--src:'.($none ? '#aab2bf' : (self::SOURCE_COLORS[$source] ?? '#7b8494')).'">'.e($none ? 'не указан' : $source).'</span>';
    }

    public static function enabled(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null && in_array($user->role, self::ROLES, true);
    }

    /** Закупку, себестоимость и прибыль видят только руководитель и владелец (решение 28.10.2026) */
    public static function seesCost(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null && in_array($user->role, ['sneaker_head', 'sneaker_owner'], true);
    }

    /** Бренд по первому слову названия модели — как brandOf() в прототипе дизайнера: «NIKE dunk…» и «nike air…» — Nike
        (в поле «бренд» склада часто всё название целиком и разный регистр) */
    public static function brandOf(string $name): string
    {
        $first = mb_strtoupper((string) (preg_split('/\s+/u', trim($name), 2)[0] ?? ''));
        $map = ['NEW' => 'New Balance', 'CALVIN' => 'Calvin Klein', 'NIKE' => 'Nike', 'WMNS' => 'Nike', 'AIR' => 'Nike', 'ADIDAS' => 'Adidas',
            'PUMA' => 'Puma', 'JORDAN' => 'Jordan', 'LACOSTE' => 'Lacoste', 'KAPPA' => 'Kappa', 'SAUCONY' => 'Saucony', 'GUESS' => 'Guess',
            'SALOMON' => 'Salomon', 'VANS' => 'Vans', 'REEBOK' => 'Reebok', 'ASICS' => 'Asics', 'CONVERSE' => 'Converse', 'FILA' => 'Fila',
            'LI-NING' => 'Li-Ning', 'MERELL' => 'Merrell', 'MERRELL' => 'Merrell', 'ONITSUKA' => 'Onitsuka Tiger', 'TIMBERLAND' => 'Timberland'];

        return $map[$first] ?? ($first === '' ? 'Без бренда' : mb_convert_case(mb_strtolower($first), MB_CASE_TITLE));
    }

    public static function icon(string $name, string $cls = ''): string
    {
        return '<svg class="ns-icon'.($cls !== '' ? ' '.$cls : '').'" viewBox="0 0 24 24" aria-hidden="true">'.(self::ICONS[$name] ?? '').'</svg>';
    }

    /** Инициалы для кружка: «Ксения Дубовцева» → «КД» */
    public static function initials(string $name): string
    {
        $parts = preg_split('/[\s\-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(fn ($p) => mb_strtoupper(mb_substr(preg_replace('/[^\p{L}]/u', '', $p), 0, 1)), $parts);

        return mb_substr(implode('', array_filter($letters)), 0, 2) ?: '?';
    }

    /**
     * Боковое меню по ролям (как в прототипе: Магазин / Склад / Сделки / Руководителю / Система). Пункты — те же, что
     * роль видела в прежней верхней панели; закупки и аналитика — руководителю (в них закупочные цены).
     *
     * @return array<string, list<array{route: string, label: string, icon: string, active: string, count?: string}>>
     */
    public static function nav(User $user): array
    {
        $role = $user->role;
        $shop = in_array($role, ['sneaker_head', 'sneaker_operator'], true);
        $head = $role === 'sneaker_head';
        $owner = $role === 'sneaker_owner';
        $item = fn (string $route, string $label, string $icon, string $active, ?string $count = null) => Route::has($route)
            ? array_filter(['route' => $route, 'label' => $label, 'icon' => $icon, 'active' => $active, 'count' => $count], fn ($v) => $v !== null)
            : null;

        $groups = [
            'Магазин' => [
                $shop ? $item('sale.quick', 'Продажа', 'sell', 'sale.quick') : null,
                $shop || $owner ? $item('sale.day', 'Продажи за день', 'cal', 'sale.day') : null,
                $shop ? $item('scan', 'Скан', 'scan', 'scan') : null,
            ],
            'Склад' => [
                $shop ? $item('warehouse.index', 'Склад', 'box', 'warehouse.index|warehouse.unlinked|warehouse.reorder|warehouse.consignments|warehouse.import*|onec.*', self::count(self::models($user))) : null,
                $shop ? $item('warehouse.receiving', 'Приёмка', 'inbox', 'warehouse.receiving*') : null,
                $head ? $item('purchases.kanban', 'Закупки', 'truck', 'purchases.*', self::count(self::inTransitPairs($user))) : null,
                $head ? $item('warehouse.analytics', 'Аналитика', 'chart', 'warehouse.analytics*') : null,
            ],
            'Сделки' => [
                $shop ? $item('deals.kanban', 'Продажи · канбан', 'kanban', 'deals.*') : null,
                $shop ? $item('tasks.index', 'Дела', 'tasks', 'tasks.*') : null,
            ],
            'Руководителю' => [
                $head || $owner ? $item('owner.dashboard', 'Сводка', 'home', 'owner.dashboard') : null,
                $head ? $item('sneaker.report', 'Отчёты', 'report', 'sneaker.report*') : null,
            ],
            'Система' => [
                $head ? $item('settings.users.index', 'Пользователи', 'users', 'settings.users.*') : null,
                $item('help.index', 'Помощь', 'help', 'help.*'),
            ],
        ];

        return array_filter(array_map(fn ($items) => array_values(array_filter($items)), $groups));
    }

    /** Активен ли пункт меню: шаблоны имён маршрутов через «|» */
    public static function active(string $patterns): bool
    {
        return request()->routeIs(...explode('|', $patterns));
    }

    /** Моделей на складе — как «Товаров» на странице склада: бренд + модель среди позиций склада */
    public static function models(User $user): int
    {
        return (int) DB::table('warehouse_items')->where('account_id', $user->account_id)
            ->selectRaw("COUNT(DISTINCT LOWER(TRIM(CONCAT(COALESCE(brand,''), '|', COALESCE(model,''))))) AS n")->value('n');
    }

    /** Пар в пути — как «В пути» на складе: закупки не закрыты и ещё не заведены на склад */
    public static function inTransitPairs(User $user): int
    {
        return (int) Purchase::query()->where('account_id', $user->account_id)->whereNull('closed_at')->whereNull('stocked_at')->sum('quantity');
    }

    public static function unread(User $user): int
    {
        return UserNotification::query()->where('account_id', $user->account_id)->where('user_id', $user->id)->where('is_read', 0)->count();
    }

    private static function count(int $n): ?string
    {
        return $n > 0 ? number_format($n, 0, ',', ' ') : null;
    }
}
