<?php

namespace Library\Adapters\Security;

use Library\Application\Ports\PasswordHasher;

final class BcryptPasswordHasher implements PasswordHasher
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
