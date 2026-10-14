<?php

namespace Library\Application\Ports;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
