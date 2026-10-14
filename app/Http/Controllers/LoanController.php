<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Application\Errors\ApiException;
use Library\Application\Ports\Clock;
use Library\Application\Ports\LibraryRepository;

final class LoanController
{
    public function __construct(
        private readonly LibraryRepository $library,
        private readonly Clock $clock,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:3'],
            'items.*.book_id' => ['required', 'uuid'],
        ]);
        $bookIds = array_column($data['items'], 'book_id');
        if (count(array_unique($bookIds)) !== count($bookIds)) {
            throw new ApiException('DUPLICATE_BOOK_IN_LOAN', 'No se puede repetir un libro en el mismo préstamo.', 422, [
                ['field' => 'items', 'issue' => 'libros duplicados'],
            ]);
        }
        $loan = $this->library->createLoan(
            $data['member_id'],
            $request->attributes->get('actor_id'),
            $bookIds,
            $this->clock->now(),
        );

        return response()->json($this->formatLoan($loan), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'in:open,overdue,closed'],
            'member_id' => ['sometimes', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'size' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        if (isset($filters['from'], $filters['to']) && $filters['to'] < $filters['from']) {
            throw new ApiException('INVALID_DATE_RANGE', 'La fecha final debe ser igual o posterior a la fecha inicial.', 422);
        }
        $result = $this->library->loans($filters + ['page' => 1, 'size' => 20]);
        $result['items'] = array_map(fn (object $loan): array => $this->formatLoan($loan), $result['items']);

        return response()->json($result);
    }

    public function show(string $id): JsonResponse
    {
        $loan = $this->library->loan($id);
        if (! $loan) {
            throw new ApiException('NOT_FOUND', 'No se encontró el préstamo.', 404);
        }

        return response()->json($this->formatLoan($loan));
    }

    public function returnItem(string $loanId, string $itemId): JsonResponse
    {
        $item = $this->library->returnItem($loanId, $itemId, $this->clock->now());
        if (! $item) {
            throw new ApiException('NOT_FOUND', 'No se encontró la línea del préstamo.', 404);
        }

        return response()->json($item);
    }

    public function overdue(): JsonResponse
    {
        return response()->json(['items' => $this->library->overdue()]);
    }

    private function formatLoan(object $loan): array
    {
        return [
            'id' => $loan->id,
            'member_id' => $loan->member_id,
            'member_name' => $loan->member_name,
            'loaned_by_user_id' => $loan->loaned_by_user_id,
            'loaned_at' => \Illuminate\Support\Carbon::parse($loan->loaned_at)->utc()->toISOString(),
            'status' => $loan->status,
            'items' => array_map(static fn (object $item): array => [
                'id' => $item->id,
                'book_id' => $item->book_id,
                'book_title' => $item->book_title,
                'isbn' => trim($item->isbn),
                'category_name' => $item->category_name,
                'due_at' => \Illuminate\Support\Carbon::parse($item->due_at)->utc()->toISOString(),
                'returned_at' => $item->returned_at === null
                    ? null
                    : \Illuminate\Support\Carbon::parse($item->returned_at)->utc()->toISOString(),
            ], $loan->items),
        ];
    }
}
