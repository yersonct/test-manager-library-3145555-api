<?php

namespace App\Http\Middleware;

use App\Support\ApiExceptionRenderer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array($request->attributes->get('actor_role'), $roles, true)) {
            return ApiExceptionRenderer::response('FORBIDDEN_ROLE', 'No tiene permiso para realizar esta operación.', 403);
        }

        return $next($request);
    }
}
