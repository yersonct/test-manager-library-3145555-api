<?php

namespace Library\Application\Ports;

interface BookRepository
{
    public function availableBookIds(array $ids): array;
}
