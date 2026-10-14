<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => true,
            'report' => false,
        ],
        'covers' => [
            'driver' => 'local',
            'root' => env('COVERS_DIR', storage_path('app/covers')),
            'throw' => true,
            'report' => false,
        ],
    ],
    'links' => [],
];
