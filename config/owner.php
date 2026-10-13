<?php

// Сводка владельца. Пространство потолков — отдельный Account; владелец (users.all_businesses) читает его
// статистику, не входя в само пространство.
return [
    'ceilings_account_id' => (int) env('OWNER_CEILINGS_ACCOUNT_ID', 1),

    // Реклама потолков (ключи — из дашборда БлагоДар, только в .env сервера). Источник без ключей не собирается.
    'marketing' => [
        'vk_ads' => [
            'client_id' => env('OWNER_VK_ADS_CLIENT_ID'),
            'client_secret' => env('OWNER_VK_ADS_CLIENT_SECRET'),
            'agency_client_name' => env('OWNER_VK_ADS_AGENCY_CLIENT_NAME'),
        ],
        // VK Реклама «Шагового» (кроссовки), кабинет 22322812 — в нём и старые кампании БлагоДара: берём только с 01.10.2026
        'vk_ads_shag' => [
            'client_id' => env('OWNER_SHAG_VK_CLIENT_ID'),
            'client_secret' => env('OWNER_SHAG_VK_CLIENT_SECRET'),
            'agency_client_name' => null,
            'plans_since' => '2026-10-01',
            'token_file' => 'vk_ads_shag_token.json',
        ],
        'avito' => [
            'client_id' => env('OWNER_AVITO_CLIENT_ID'),
            'client_secret' => env('OWNER_AVITO_CLIENT_SECRET'),
        ],
        'direct' => [
            'token' => env('OWNER_DIRECT_TOKEN'),
            'client_login' => env('OWNER_DIRECT_CLIENT_LOGIN'),
            'sandbox' => (bool) env('OWNER_DIRECT_SANDBOX', false),
        ],
        // Google-таблица заявок (замеров) по дням и источникам, листы «ИЖ <Месяц> <Год>», открыта по ссылке
        'leads_sheet' => [
            'id' => env('OWNER_LEADS_SHEET_ID'),
        ],
    ],

    // Незаключённые договоры по замерщикам — готовая сводка CRM БлагоДар (служебный токен на чтение отчёта).
    'nonclosures' => [
        'url' => env('OWNER_NONCLOSURES_URL'),
        'token' => env('OWNER_NONCLOSURES_TOKEN'),
    ],
];
