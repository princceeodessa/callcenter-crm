<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()?->role === 'measurer' ? 'calendar.index' : 'deals.kanban');
        }

        return $next($request);
    }
}
