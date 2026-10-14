<?php

namespace Library\Application\Ports;

use DateTimeImmutable;

interface AccessTokenIssuer
{
    public function issue(string $userId, string $role, DateTimeImmutable $expiresAt): string;

    /** @return array{sub: string, role: string, exp: int}|null */
    public function verify(string $token): ?array;
}
