<?php

namespace Library\Application\Ports;

use DateTimeImmutable;

interface ReportReader
{
    public function mostBorrowed(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
