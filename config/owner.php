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

    // Прибыль договоров (потолки) — готовые итоги CRM БлагоДар (служебный токен на чтение отчёта, только в .env сервера).
    'contract_profit' => [
        'url' => env('BLAGODAR_PROFIT_URL'),
        'token' => env('BLAGODAR_PROFIT_TOKEN'),
        // строки без ставки или цены закупки — выгрузка scripts/blagodar/cp_uncosted.js (раз в час, только чтение);
        // по ним прибыль досчитывается у нас (ContractProfitFill). Нет файла — досчёта нет.
        'uncosted_file' => env('BLAGODAR_UNCOSTED_FILE', '/var/lib/callcenter-crm-owner/cp_uncosted.json'),
    ],

    // Общие расходы потолков сверх расходов договоров (со слов руководства 26.10.2026) — «Заработаем» минус они = «Чистыми».
    // Кассиры — за день (× дней периода), остальное — в месяц (на неполный месяц — по дням). Реклама — из сборщиков CRM.
    'overhead' => [
        'cashiers_per_day' => 4700,
        'monthly' => [
            'marketing' => ['Маркетинг', 300000],
            'radio' => ['Радио', 68000],
            'newspapers' => ['Газеты', 20000],
            'dgis' => ['2ГИС', 10000],
        ],
    ],

    // Работа главного по замерщикам — готовые итоги CRM БлагоДар (тот же адрес, свой токен на чтение, только в .env).
    'head_work' => [
        'url' => env('BLAGODAR_HEAD_WORK_URL'),
        'token' => env('BLAGODAR_HEAD_WORK_TOKEN'),
    ],
];
