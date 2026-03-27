<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireProjectingAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, ['admin', 'constructor'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
