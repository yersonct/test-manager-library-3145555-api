<?php

namespace Library\Adapters\System;

use DateTimeImmutable;
use DateTimeZone;
use Library\Application\Ports\Clock;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
