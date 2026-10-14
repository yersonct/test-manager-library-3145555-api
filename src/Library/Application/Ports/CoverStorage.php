<?php

namespace Library\Application\Ports;

interface CoverStorage
{
    public function put(string $contents, string $extension): string;

    public function get(string $key): ?string;

    public function delete(string $key): void;
}
