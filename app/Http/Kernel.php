<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        \App\Http\Middleware\TrustProxies::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'admin' => \App\Http\Middleware\RequireAdmin::class,
        'admin.only' => \App\Http\Middleware\RequireOnlyAdmin::class,
        'desk' => \App\Http\Middleware\RequireDeskAccess::class,
        'calendar' => \App\Http\Middleware\RequireCalendarAccess::class,
        'nonclosure' => \App\Http\Middleware\RequireNonClosureAccess::class,
        'projecting' => \App\Http\Middleware\RequireProjectingAccess::class,
        'reports' => \App\Http\Middleware\RequireReportAccess::class,
    ];
}
