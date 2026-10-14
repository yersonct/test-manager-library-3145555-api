<?php

namespace Library\Application\Ports;

use DateTimeImmutable;

interface LibraryRepository
{
    public function userByUsername(string $username): ?object;
    public function createLibrarian(string $username, string $passwordHash): object;
    public function provisionAdmin(string $username, string $passwordHash): void;
    public function categories(): array;
    public function books(array $filters): array;
    public function book(string $id): ?object;
    public function createBook(array $data): object;
    public function updateBook(string $id, array $data): ?object;
    public function deleteBook(string $id, DateTimeImmutable $at): bool;
    public function setCover(string $id, ?string $key): bool;
    public function member(string $id): ?object;
    public function members(array $filters): array;
    public function createMember(array $data): object;
    public function loan(string $id): ?object;
    public function loans(array $filters): array;
    public function overdue(): array;
    public function createLoan(string $memberId, string $userId, array $bookIds, DateTimeImmutable $now): object;
    public function returnItem(string $loanId, string $itemId, DateTimeImmutable $now): ?object;
    public function report(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
