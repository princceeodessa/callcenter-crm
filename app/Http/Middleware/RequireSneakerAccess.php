<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Страницы кроссовок «только посмотреть», которые нужны всем трём ролям пространства:
 * руководителю, продавцу и владельцу (например, «Продажи за день»).
 * Владелец по-прежнему не попадает в рабочие страницы (склад, продажа, закупки) — там 'purchases'.
 */
class RequireSneakerAccess
{
    public const ROLES = ['sneaker_head', 'sneaker_operator', 'sneaker_owner'];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, self::ROLES, true)) {
            abort(403);
        }

        return $next($request);
    }
}
