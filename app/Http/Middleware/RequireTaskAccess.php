<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireTaskAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['admin', 'main_operator', 'operator', 'documents_operator', 'sneaker_head', 'sneaker_operator'], true)) {
            abort(403);
        }

        return $next($request);
    }
}

