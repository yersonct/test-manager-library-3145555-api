<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Application\Ports\AccessTokenIssuer;
use Library\Application\Ports\Clock;
use Library\Application\Ports\LibraryRepository;
use Library\Application\Ports\PasswordHasher;

final class AuthController
{
    public function __construct(
        private readonly LibraryRepository $library,
        private readonly PasswordHasher $hasher,
        private readonly AccessTokenIssuer $tokens,
        private readonly Clock $clock,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:120', 'not_regex:/^\s*$/'],
            'password' => ['required', 'string'],
        ]);
        if (strlen($data['password']) > 72) {
            return \App\Support\ApiExceptionRenderer::response(
                'INVALID_CREDENTIALS',
                'Usuario o contraseña incorrectos.',
                401,
            );
        }
        $user = $this->library->userByUsername($data['username']);
        if (! $user || ! $this->hasher->verify($data['password'], $user->password_hash)) {
            return \App\Support\ApiExceptionRenderer::response('INVALID_CREDENTIALS', 'Usuario o contraseña incorrectos.', 401);
        }
        $expiresAt = $this->clock->now()->modify('+8 hours');

        return response()->json([
            'access_token' => $this->tokens->issue($user->id, $user->role, $expiresAt),
            'token_type' => 'Bearer',
            'expires_in' => 28800,
            'role' => $user->role,
        ]);
    }
}
