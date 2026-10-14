<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Application\Errors\ApiException;
use Library\Application\Ports\LibraryRepository;

final class MemberController
{
    public function __construct(private readonly LibraryRepository $library)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'size' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json($this->library->members($filters + ['page' => 1, 'size' => 20]));
    }

    public function show(string $id): JsonResponse
    {
        $member = $this->library->member($id);
        if (! $member) {
            throw new ApiException('NOT_FOUND', 'No se encontró el socio.', 404);
        }

        return response()->json($member);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:200', 'not_regex:/^\s*$/'],
            'document_number' => ['required', 'string', 'max:30', 'not_regex:/^\s*$/'],
        ]);

        return response()->json($this->library->createMember($data), 201);
    }
}
