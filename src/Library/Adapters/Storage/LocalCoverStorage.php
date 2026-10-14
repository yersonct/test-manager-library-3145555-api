<?php

namespace Library\Adapters\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Library\Application\Ports\CoverStorage;

final class LocalCoverStorage implements CoverStorage
{
    public function put(string $contents, string $extension): string
    {
        $key = Str::uuid().'.'.$extension;
        Storage::disk('covers')->put($key, $contents);

        return $key;
    }

    public function get(string $key): ?string
    {
        return Storage::disk('covers')->exists($key)
            ? Storage::disk('covers')->get($key)
            : null;
    }

    public function delete(string $key): void
    {
        Storage::disk('covers')->delete($key);
    }
}
