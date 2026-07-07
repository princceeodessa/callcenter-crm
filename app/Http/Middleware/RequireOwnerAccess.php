<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Доступ к сводной панели владельца. Владелец (sneaker_owner) видит ТОЛЬКО эту
 * страницу; руководителю (sneaker_head) тоже разрешаем — для контроля/предпросмотра.
 */
class RequireOwnerAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['sneaker_owner', 'sneaker_head'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
