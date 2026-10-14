<?php

namespace Library\Adapters\Persistence;

use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Library\Application\Errors\ApiException;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\LibraryRepository;

final class EloquentLibraryRepository implements LibraryRepository, BookRepository
{
    public function userByUsername(string $username): ?object
    {
        return DB::table('library.user')->where('username', mb_strtolower(trim($username)))->first();
    }

    public function createLibrarian(string $username, string $passwordHash): object
    {
        $row = (object) [
            'id' => (string) Str::uuid(),
            'username' => mb_strtolower(trim($username)),
            'role' => 'librarian',
        ];
        $this->insert('library.user', [
            'id' => $row->id,
            'username' => $row->username,
            'password_hash' => $passwordHash,
            'role' => $row->role,
        ]);

        return $row;
    }

    public function provisionAdmin(string $username, string $passwordHash): void
    {
        DB::table('library.user')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'username' => mb_strtolower(trim($username)),
            'password_hash' => $passwordHash,
            'role' => 'admin',
        ]);
    }

    public function categories(): array
    {
        return DB::table('library.category')->orderBy('name')->get()->all();
    }

    public function books(array $filters): array
    {
        $query = DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->whereNull('b.deleted_at')
            ->select('b.*', 'c.name as category_name');
        if (! empty($filters['q'])) {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']).'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('b.title', 'ilike', $needle)->orWhere('b.author', 'ilike', $needle);
            });
        }
        if (! empty($filters['category_id'])) {
            $query->where('b.category_id', $filters['category_id']);
        }
        $total = (clone $query)->count();
        $page = $filters['page'];
        $size = $filters['size'];
        $items = $query->orderBy('b.title')->offset(($page - 1) * $size)->limit($size)->get()->all();

        return compact('items', 'page', 'size', 'total');
    }

    public function book(string $id): ?object
    {
        return DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->whereNull('b.deleted_at')->where('b.id', $id)
            ->select('b.*', 'c.name as category_name')->first();
    }

    public function createBook(array $data): object
    {
        $id = (string) Str::uuid();
        $this->insert('library.book', [
            'id' => $id,
            'title' => trim($data['title']),
            'author' => trim($data['author']),
            'isbn' => $data['isbn'],
            'category_id' => $data['category_id'],
            'total_copies' => $data['total_copies'],
            'available_copies' => $data['total_copies'],
            'cover_key' => null,
            'deleted_at' => null,
        ]);

        return $this->book($id) ?? throw new \LogicException('The created book could not be retrieved.');
    }

    public function updateBook(string $id, array $data): ?object
    {
        return DB::transaction(function () use ($id, $data): ?object {
            $book = DB::table('library.book')->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $book) {
                return null;
            }
            $available = (int) $book->available_copies + (int) $data['total_copies'] - (int) $book->total_copies;
            if ($available < 0) {
                throw new ApiException('VALIDATION_ERROR', 'El total no puede ser menor que los ejemplares prestados.', 422, [
                    ['field' => 'total_copies', 'issue' => 'inferior a los ejemplares actualmente prestados'],
                ]);
            }
            DB::table('library.book')->where('id', $id)->update([
                'title' => trim($data['title']),
                'author' => trim($data['author']),
                'category_id' => $data['category_id'],
                'total_copies' => $data['total_copies'],
                'available_copies' => $available,
            ]);

            return $this->book($id);
        });
    }

    public function deleteBook(string $id, DateTimeImmutable $at): bool
    {
        return DB::transaction(function () use ($id, $at): bool {
            $book = DB::table('library.book')->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $book) {
                return false;
            }
            $openLoans = DB::table('library.loan_item')->where('book_id', $id)->whereNull('returned_at')->exists();
            if ($openLoans) {
                throw new ApiException('BOOK_HAS_OPEN_LOANS', 'No se puede dar de baja un libro con préstamos abiertos.', 409);
            }
            DB::table('library.book')->where('id', $id)->update(['deleted_at' => $at->format('Y-m-d H:i:sP')]);

            return true;
        });
    }

    public function setCover(string $id, ?string $key): bool
    {
        return DB::table('library.book')->where('id', $id)->whereNull('deleted_at')
            ->update(['cover_key' => $key]) === 1;
    }

    public function member(string $id): ?object
    {
        $row = DB::table('library.member')->where('id', $id)->first();
        if ($row) {
            $row->active_loans = DB::table('library.loan_item as li')
                ->join('library.loan as l', 'l.id', '=', 'li.loan_id')
                ->where('l.member_id', $id)->whereNull('li.returned_at')->count();
        }

        return $row;
    }

    public function members(array $filters): array
    {
        $query = DB::table('library.member');
        if (! empty($filters['q'])) {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']).'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('full_name', 'ilike', $needle)->orWhere('document_number', 'ilike', $needle);
            });
        }
        $total = (clone $query)->count();
        $page = $filters['page'];
        $size = $filters['size'];
        $items = $query->orderBy('full_name')->offset(($page - 1) * $size)->limit($size)->get()->all();

        return compact('items', 'page', 'size', 'total');
    }

    public function createMember(array $data): object
    {
        $id = (string) Str::uuid();
        $this->insert('library.member', [
            'id' => $id,
            'full_name' => trim($data['full_name']),
            'document_number' => trim($data['document_number']),
        ]);

        return $this->member($id) ?? throw new \LogicException('The created member could not be retrieved.');
    }

    public function loan(string $id): ?object
    {
        $loan = DB::table('library.loan as l')
            ->join('library.member as m', 'm.id', '=', 'l.member_id')
            ->where('l.id', $id)
            ->select('l.*', 'm.full_name as member_name')->first();
        if (! $loan) {
            return null;
        }
        $loan->items = DB::table('library.loan_item')->where('loan_id', $id)->orderBy('id')->get()->all();
        $loan->status = $this->loanStatus($loan->items);

        return $loan;
    }

    public function loans(array $filters): array
    {
        $query = DB::table('library.loan as l')
            ->join('library.member as m', 'm.id', '=', 'l.member_id')
            ->select('l.*', 'm.full_name as member_name');
        if (! empty($filters['member_id'])) {
            $query->where('l.member_id', $filters['member_id']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('l.loaned_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('l.loaned_at', '<=', $filters['to']);
        }
        $page = $filters['page'];
        $size = $filters['size'];
        if (! empty($filters['status'])) {
            $all = (clone $query)->orderByDesc('l.loaned_at')->get()->all();
            $filtered = [];
            foreach ($all as $item) {
                $item->items = DB::table('library.loan_item')->where('loan_id', $item->id)->get()->all();
                $item->status = $this->loanStatus($item->items);
                if ($item->status === $filters['status']) {
                    $filtered[] = $item;
                }
            }
            $total = count($filtered);
            $items = array_slice($filtered, ($page - 1) * $size, $size);
        } else {
            $total = (clone $query)->count();
            $items = $query->orderByDesc('l.loaned_at')->offset(($page - 1) * $size)->limit($size)->get()->all();
            foreach ($items as $item) {
                $item->items = DB::table('library.loan_item')->where('loan_id', $item->id)->get()->all();
                $item->status = $this->loanStatus($item->items);
            }
        }

        return compact('items', 'page', 'size', 'total');
    }

    public function overdue(): array
    {
        return DB::table('library.loan_item as li')
            ->join('library.loan as l', 'l.id', '=', 'li.loan_id')
            ->join('library.member as m', 'm.id', '=', 'l.member_id')
            ->whereNull('li.returned_at')->where('li.due_at', '<', now('UTC'))
            ->select('l.id as loan_id', 'li.id as item_id', 'li.book_title', 'm.full_name as member_name', 'li.due_at')
            ->orderBy('li.due_at')->get()->map(function ($row): object {
                $row->days_late = max(1, (int) \Illuminate\Support\Carbon::parse($row->due_at)->diffInDays(now('UTC')));

                return $row;
            })->all();
    }

    public function createLoan(string $memberId, string $userId, array $bookIds, DateTimeImmutable $now): object
    {
        return DB::transaction(function () use ($memberId, $userId, $bookIds, $now): object {
            $member = DB::table('library.member')->where('id', $memberId)->lockForUpdate()->first();
            if (! $member) {
                throw new ApiException('NOT_FOUND', 'No se encontró el socio.', 404);
            }
            $hasOverdue = DB::table('library.loan_item as li')->join('library.loan as l', 'l.id', '=', 'li.loan_id')
                ->where('l.member_id', $memberId)->whereNull('li.returned_at')->where('li.due_at', '<', $now)->exists();
            if ($hasOverdue) {
                throw new ApiException('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos pendientes de devolución.', 409);
            }
            $active = DB::table('library.loan_item as li')->join('library.loan as l', 'l.id', '=', 'li.loan_id')
                ->where('l.member_id', $memberId)->whereNull('li.returned_at')->count();
            if ($active + count($bookIds) > 3) {
                throw new ApiException('MEMBER_LIMIT_EXCEEDED', 'El socio supera el límite de tres libros sin devolver.', 422, [
                    ['field' => 'items', 'issue' => "libros activos actuales: $active"],
                ]);
            }

            $books = DB::table('library.book as b')->join('library.category as c', 'c.id', '=', 'b.category_id')
                ->whereIn('b.id', $bookIds)->whereNull('b.deleted_at')->orderBy('b.id')->lockForUpdate()
                ->select('b.*', 'c.name as category_name')->get()->keyBy('id');
            if ($books->count() !== count($bookIds)) {
                throw new ApiException('NOT_FOUND', 'Uno o más libros no existen o están dados de baja.', 404);
            }
            foreach ($bookIds as $bookId) {
                $updated = DB::table('library.book')->where('id', $bookId)->whereNull('deleted_at')
                    ->where('available_copies', '>', 0)->decrement('available_copies');
                if ($updated !== 1) {
                    throw new ApiException('BOOK_NOT_AVAILABLE', 'No quedan ejemplares disponibles.', 409);
                }
            }
            $loanId = (string) Str::uuid();
            DB::table('library.loan')->insert([
                'id' => $loanId,
                'member_id' => $memberId,
                'loaned_by_user_id' => $userId,
                'loaned_at' => $now->format('Y-m-d H:i:sP'),
            ]);
            foreach ($bookIds as $bookId) {
                $book = $books[$bookId];
                DB::table('library.loan_item')->insert([
                    'id' => (string) Str::uuid(),
                    'loan_id' => $loanId,
                    'book_id' => $bookId,
                    'book_title' => $book->title,
                    'isbn' => $book->isbn,
                    'category_name' => $book->category_name,
                    'due_at' => $now->modify('+14 days')->format('Y-m-d H:i:sP'),
                    'returned_at' => null,
                ]);
            }

            return $this->loan($loanId) ?? throw new \LogicException('The created loan could not be retrieved.');
        }, 3);
    }

    public function returnItem(string $loanId, string $itemId, DateTimeImmutable $now): ?object
    {
        return DB::transaction(function () use ($loanId, $itemId, $now): ?object {
            $item = DB::table('library.loan_item')->where('id', $itemId)->where('loan_id', $loanId)->lockForUpdate()->first();
            if (! $item) {
                return null;
            }
            $updated = DB::table('library.loan_item')->where('id', $itemId)->whereNull('returned_at')
                ->update(['returned_at' => $now->format('Y-m-d H:i:sP')]);
            if ($updated !== 1) {
                throw new ApiException('ITEM_ALREADY_RETURNED', 'Esta línea ya fue devuelta.', 409);
            }
            $restocked = DB::table('library.book')->where('id', $item->book_id)
                ->whereColumn('available_copies', '<', 'total_copies')->increment('available_copies');
            if ($restocked !== 1) {
                throw new \LogicException('The return would violate the book inventory invariant.');
            }

            return DB::table('library.loan_item')->where('id', $itemId)->first();
        });
    }

    public function report(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return DB::table('library.loan as l')->join('library.loan_item as li', 'li.loan_id', '=', 'l.id')
            ->whereDate('l.loaned_at', '>=', $from->format('Y-m-d'))
            ->whereDate('l.loaned_at', '<=', $to->format('Y-m-d'))
            ->groupBy('li.book_id', 'li.book_title', 'li.category_name')
            ->select('li.book_id', 'li.book_title', 'li.category_name', DB::raw('COUNT(*)::integer AS times_borrowed'))
            ->orderByDesc('times_borrowed')->orderBy('li.book_title')->get()->all();
    }

    public function availableBookIds(array $ids): array
    {
        return DB::table('library.book')->whereIn('id', $ids)->whereNull('deleted_at')
            ->where('available_copies', '>', 0)->pluck('id')->all();
    }

    private function loanStatus(array $items): string
    {
        if ($items !== [] && collect($items)->every(fn (object $item): bool => $item->returned_at !== null)) {
            return 'closed';
        }
        foreach ($items as $item) {
            if ($item->returned_at === null && \Illuminate\Support\Carbon::parse($item->due_at)->lt(now('UTC'))) {
                return 'overdue';
            }
        }

        return 'open';
    }

    private function insert(string $table, array $values): void
    {
        try {
            DB::table($table)->insert($values);
        } catch (QueryException $exception) {
            $constraint = $exception->errorInfo[2] ?? '';
            if (str_contains($constraint, 'uq_book_active_isbn')) {
                throw new ApiException('ISBN_TAKEN', 'Ya existe un libro activo con ese ISBN.', 409);
            }
            if (str_contains($constraint, 'member_document_number')) {
                throw new ApiException('DOCUMENT_TAKEN', 'Ya existe un socio con ese documento.', 409);
            }
            if (str_contains($constraint, 'user_username')) {
                throw new ApiException('USERNAME_TAKEN', 'Ese nombre de usuario ya está registrado.', 409);
            }
            throw $exception;
        }
    }
}
