<?php

/*
 * CRM «БлагоДар» — поиск договоров 1С для колл-центра (26.11.2026, docs/agent/kc-site-contract-search.md в репозитории
 * CRM «БлагоДар»). Учётка роли «Сайт колл-центра» (KC_SITE): одно право — поиск договоров без сканов. Логин и пароль
 * передаёт владелец CRM; они только в .env сервера, не в коде и не в git.
 */
return [
    // адрес API — из переменной: CRM может переехать, сайт переживёт это сменой одной строки
    'api' => rtrim((string) env('BLAGODAR_CRM_API', ''), '/'),
    'login' => env('BLAGODAR_CRM_LOGIN'),
    'password' => env('BLAGODAR_CRM_PASSWORD'),
    // одна пара токенов на весь сайт — в файле (кэш Laravel стирается при каждой выкладке, optimize:clear)
    'token_file' => env('BLAGODAR_CRM_TOKEN_FILE', storage_path('app/blagodar-crm/tokens.json')),
    // тот же запрос в течение стольких секунд отдаём по уже начатому поиску: каждый поиск — сеансы во всех базах 1С
    'reuse_seconds' => 180,
];
