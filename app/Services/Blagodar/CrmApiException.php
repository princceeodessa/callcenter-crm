<?php

namespace App\Services\Blagodar;

use RuntimeException;

/** Ответ CRM «БлагоДар» не 2xx (status 0 — сеть, CRM не ответила). message — текст CRM для человека, если был. */
class CrmApiException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
