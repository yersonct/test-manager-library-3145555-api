<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Library\Application\Ports\LibraryRepository;
use Library\Application\Ports\PasswordHasher;

final class ProvisionAdmin extends Command
{
    protected $signature = 'library:provision-admin';

    protected $description = 'Crea el administrador inicial a partir de ADMIN_USERNAME y ADMIN_PASSWORD.';

    public function handle(LibraryRepository $library, PasswordHasher $hasher): int
    {
        $username = trim((string) config('library.admin_username'));
        $password = (string) config('library.admin_password');
        if ($username === '' || mb_strlen($username) > 120 || $password === '') {
            $this->error('Defina ADMIN_USERNAME y ADMIN_PASSWORD en el entorno.');

            return self::FAILURE;
        }
        $existing = $library->userByUsername($username);
        if ($existing) {
            if ($existing->role !== 'admin') {
                $this->error('El nombre configurado ya pertenece a un usuario que no es administrador.');

                return self::FAILURE;
            }
            $this->info('El usuario administrador ya existe; no se realizaron cambios.');

            return self::SUCCESS;
        }
        $library->provisionAdmin($username, $hasher->hash($password));
        $this->info('Administrador inicial creado.');

        return self::SUCCESS;
    }
}
