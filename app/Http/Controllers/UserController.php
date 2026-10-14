<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Application\Ports\LibraryRepository;
use Library\Application\Ports\PasswordHasher;

final class UserController
{
    public function __construct(
        private readonly LibraryRepository $library,
        private readonly PasswordHasher $hasher,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:120', 'not_regex:/^\s*$/'],
            'password' => ['required', 'string'],
            'role' => ['required', 'in:librarian'],
        ]);
        if (strlen($data['password']) > 72) {
            throw new \Library\Application\Errors\ApiException(
                'VALIDATION_ERROR',
                'La contraseña no puede superar 72 bytes.',
                422,
                [['field' => 'password', 'issue' => 'máximo 72 bytes']],
            );
        }
        $user = $this->library->createLibrarian($data['username'], $this->hasher->hash($data['password']));

        return response()->json($user, 201);
    }
}
