<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        // "admin" and "main_operator" are privileged roles.
        if (!$user || !in_array($user->role, ['admin', 'main_operator'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
