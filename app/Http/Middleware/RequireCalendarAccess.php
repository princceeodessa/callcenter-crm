<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireCalendarAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        // Calendar is visible to admin, call-center (operators/main_operator), and measurers.
        if (!$user || !in_array($user->role, ['admin', 'main_operator', 'operator', 'measurer'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
