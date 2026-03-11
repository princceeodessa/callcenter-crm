<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireDeskAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || $user->role === 'measurer') {
            abort(403);
        }

        return $next($request);
    }
}
