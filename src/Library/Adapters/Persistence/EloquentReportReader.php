<?php

namespace Library\Adapters\Persistence;

use DateTimeImmutable;
use Library\Application\Ports\ReportReader;

final class EloquentReportReader implements ReportReader
{
    public function __construct(private readonly EloquentLibraryRepository $repository)
    {
    }

    public function mostBorrowed(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->repository->report($from, $to);
    }
}
