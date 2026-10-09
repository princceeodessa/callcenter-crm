<?php

// Сводка владельца. Пространство потолков — отдельный Account; владелец (users.all_businesses) читает его
// статистику, не входя в само пространство.
return [
    'ceilings_account_id' => (int) env('OWNER_CEILINGS_ACCOUNT_ID', 1),
];
