<?php

namespace App\Support\Export;

use App\Services\Owner\CeilingsKpi;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Выгрузка обращений колл-центра потолков в Excel: номер телефона, менеджер (кто первым взял лид), записался ли.
 * Нецелевые и исходящие дозвоны не попадают (CeilingsKpi::contactList). Скачивается из «Отчётов» и из сводки.
 */
final class ContactsExport
{
    /** @param Carbon $to конец периода, не включая */
    public static function download(int $accountId, Carbon $from, Carbon $to): Response
    {
        $rows = [['Номер телефона', 'Менеджер (кто принял)', 'Записался']];
        foreach ((new CeilingsKpi($accountId))->contactList($from, $to) as $r) {
            $rows[] = [$r['phone'], $r['manager'], $r['booked'] ? 'да' : 'нет'];
        }
        $last = $to->copy()->subDay();
        $title = 'Обращения КЦ '.$from->format('d.m.Y').'–'.$last->format('d.m.Y');
        $ascii = 'obrashcheniya-kc-'.$from->format('Y-m-d').'_'.$last->format('Y-m-d').'.xlsx';

        return response(SimpleXlsxWriter::build('Обращения', $rows, [24, 30, 12]), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($title.'.xlsx'),
            'Cache-Control' => 'no-store',
        ]);
    }
}
