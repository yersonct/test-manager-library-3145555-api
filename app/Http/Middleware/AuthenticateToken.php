<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Library\Application\Ports\AccessTokenIssuer;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateToken
{
    public function __construct(private readonly AccessTokenIssuer $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization', '');
        if (! preg_match('/^Bearer ([A-Za-z0-9._-]+)$/', $authorization, $matches)) {
            return \App\Support\ApiExceptionRenderer::response('UNAUTHENTICATED', 'Se requiere autenticación válida.', 401);
        }
        $claims = $this->tokens->verify($matches[1]);
        if ($claims === null) {
            return \App\Support\ApiExceptionRenderer::response('UNAUTHENTICATED', 'Se requiere autenticación válida.', 401);
        }
        $user = DB::table('library.user')->where('id', $claims['sub'])->first();
        if (! $user || $user->role !== $claims['role']) {
            return \App\Support\ApiExceptionRenderer::response('UNAUTHENTICATED', 'Se requiere autenticación válida.', 401);
        }
        $request->attributes->set('actor_id', $user->id);
        $request->attributes->set('actor_role', $user->role);

        return $next($request);
    }
}
