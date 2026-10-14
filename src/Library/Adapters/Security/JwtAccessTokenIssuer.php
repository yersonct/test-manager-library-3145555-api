<?php

namespace Library\Adapters\Security;

use DateTimeImmutable;
use Library\Application\Ports\AccessTokenIssuer;

final class JwtAccessTokenIssuer implements AccessTokenIssuer
{
    public function issue(string $userId, string $role, DateTimeImmutable $expiresAt): string
    {
        $secret = (string) config('jwt.secret');
        if (strlen($secret) < 32) {
            throw new \LogicException('JWT_SECRET debe tener al menos 32 caracteres.');
        }

        $header = $this->encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->encode(json_encode([
            'sub' => $userId,
            'role' => $role,
            'iat' => time(),
            'exp' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
        $signature = $this->encode(hash_hmac('sha256', "$header.$payload", $secret, true));

        return "$header.$payload.$signature";
    }

    public function verify(string $token): ?array
    {
        $secret = (string) config('jwt.secret');
        $parts = explode('.', $token);
        if (count($parts) !== 3 || strlen($secret) < 32) {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        $expected = $this->encode(hash_hmac('sha256', "$header.$payload", $secret, true));
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $headerData = json_decode($this->decode($header) ?: '', true);
        $claims = json_decode($this->decode($payload) ?: '', true);
        if (! is_array($headerData) || ($headerData['alg'] ?? null) !== 'HS256'
            || ! is_array($claims) || ! isset($claims['sub'], $claims['role'], $claims['exp'])
            || ! in_array($claims['role'], ['admin', 'librarian'], true)
            || ! is_int($claims['exp']) || $claims['exp'] <= time()) {
            return null;
        }

        return $claims;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
