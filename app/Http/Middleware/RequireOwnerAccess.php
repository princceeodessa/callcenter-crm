<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Доступ к сводной панели владельца. Владелец (sneaker_owner) видит ТОЛЬКО эту
 * страницу; руководителю (sneaker_head) тоже разрешаем — для контроля/предпросмотра.
 * SMM-специалист (smm) — только страницы «SMM-контент» (какие бизнесы — проверяет OwnerSmmController).
 */
class RequireOwnerAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->role === 'smm') {
            if (! $request->routeIs('owner.smm')) {
                return redirect()->route('owner.smm', ['business' => $user->smmBusinesses()[0] ?? 'ceilings']);
            }

            return $next($request);
        }

        if (! $user || ! in_array($user->role, ['sneaker_owner', 'sneaker_head'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
