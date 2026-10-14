<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Library\Application\Ports\LibraryRepository;

final class CategoryController
{
    public function __construct(private readonly LibraryRepository $library)
    {
    }

    public function __invoke(): JsonResponse
    {
        return response()->json($this->library->categories());
    }
}
