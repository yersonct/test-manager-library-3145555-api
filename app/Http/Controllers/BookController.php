<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Library\Application\Errors\ApiException;
use Library\Application\Ports\Clock;
use Library\Application\Ports\CoverStorage;
use Library\Application\Ports\LibraryRepository;

final class BookController
{
    public function __construct(
        private readonly LibraryRepository $library,
        private readonly CoverStorage $covers,
        private readonly Clock $clock,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:250'],
            'category_id' => ['sometimes', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'size' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->library->books($this->pagination($filters));
        $result['items'] = array_map(fn (object $book): array => $this->formatBook($book), $result['items']);

        return response()->json($result);
    }

    public function show(string $id): JsonResponse
    {
        $book = $this->library->book($id);
        if (! $book) {
            throw new ApiException('NOT_FOUND', 'No se encontró el libro.', 404);
        }

        return response()->json($this->formatBook($book));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:250', 'not_regex:/^\s*$/'],
            'author' => ['required', 'string', 'max:200', 'not_regex:/^\s*$/'],
            'isbn' => ['required', 'regex:/^\d{13}$/'],
            'category_id' => ['required', 'uuid', 'exists:library.category,id'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ]);
        $book = $this->library->createBook($data);

        return response()->json($this->formatBook($book), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:250', 'not_regex:/^\s*$/'],
            'author' => ['required', 'string', 'max:200', 'not_regex:/^\s*$/'],
            'category_id' => ['required', 'uuid', 'exists:library.category,id'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ]);
        $book = $this->library->updateBook($id, $data);
        if (! $book) {
            throw new ApiException('NOT_FOUND', 'No se encontró el libro.', 404);
        }

        return response()->json($this->formatBook($book));
    }

    public function destroy(string $id): JsonResponse
    {
        if (! $this->library->deleteBook($id, $this->clock->now())) {
            throw new ApiException('NOT_FOUND', 'No se encontró el libro.', 404);
        }

        return response()->json(null, 204);
    }

    public function uploadCover(Request $request, string $id): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048']]);
        $book = $this->library->book($id);
        if (! $book) {
            throw new ApiException('NOT_FOUND', 'No se encontró el libro.', 404);
        }
        $file = $request->file('file');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer(file_get_contents($file->getRealPath()));
        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new ApiException('VALIDATION_ERROR', 'La portada debe ser una imagen JPG o PNG válida.', 422);
        }
        $contents = file_get_contents($file->getRealPath());
        $key = $this->covers->put($contents, $mime === 'image/png' ? 'png' : 'jpg');
        if (! $this->library->setCover($id, $key)) {
            $this->covers->delete($key);
            throw new ApiException('NOT_FOUND', 'No se encontró el libro.', 404);
        }
        if ($book->cover_key) {
            $this->covers->delete($book->cover_key);
        }

        return response()->json($this->formatBook($this->library->book($id)));
    }

    public function cover(string $id)
    {
        $book = $this->library->book($id);
        if (! $book || ! $book->cover_key) {
            throw new ApiException('NOT_FOUND', 'No se encontró la portada.', 404);
        }
        $bytes = $this->covers->get($book->cover_key);
        if ($bytes === null) {
            throw new ApiException('NOT_FOUND', 'No se encontró la portada.', 404);
        }

        return response($bytes)->header('Content-Type', str_ends_with($book->cover_key, '.png') ? 'image/png' : 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    public function deleteCover(string $id): JsonResponse
    {
        $book = $this->library->book($id);
        if (! $book) {
            throw new ApiException('NOT_FOUND', 'No se encontró el libro.', 404);
        }
        if ($book->cover_key) {
            DB::transaction(fn () => $this->library->setCover($id, null));
            $this->covers->delete($book->cover_key);
        }

        return response()->json(null, 204);
    }

    private function pagination(array $filters): array
    {
        return $filters + ['page' => 1, 'size' => 20];
    }

    private function formatBook(object $book): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => trim($book->isbn),
            'category' => ['id' => $book->category_id, 'name' => $book->category_name],
            'total_copies' => (int) $book->total_copies,
            'available_copies' => (int) $book->available_copies,
            'cover_key' => $book->cover_key,
        ];
    }
}
