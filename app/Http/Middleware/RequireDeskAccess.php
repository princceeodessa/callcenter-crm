<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireDeskAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // SMM-специалисту CRM не открывается: его страница — «SMM-контент» (сюда попадает и главная «/»)
        if ($user && $user->role === 'smm') {
            return redirect()->route('owner.smm', ['business' => $user->smmBusinesses()[0] ?? 'ceilings']);
        }

        if (! $user || !in_array($user->role, ['admin', 'main_operator', 'operator', 'sneaker_head', 'sneaker_operator'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
