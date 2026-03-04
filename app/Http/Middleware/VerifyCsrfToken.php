<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Public webhooks must be CSRF-exempt (external services can't send Laravel CSRF tokens).
     */
    protected $except = [
        'webhooks/*',
    ];
}
