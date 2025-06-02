<?php

return [
    'laminas_microscope' => [
        'enabled' => true,
        'environment' => 'testing',
        'debug' => true,
        'storage' => [
            'path' => sys_get_temp_dir() . '/laminas-microscope-test',
            'retention_days' => 1,
        ],
        'components' => [
            'whoops' => [
                'enabled' => true,
                'show_in_production' => false,
                'editor' => 'vscode',
            ],
            'debug_bar' => [
                'enabled' => true,
                'position' => 'bottom',
                'collectors' => [
                    'time',
                    'memory',
                    'exceptions',
                    'pdo',
                    'request',
                    'config',
                    'messages',
                ],
            ],
            'microscope' => [
                'enabled' => true,
                'auto_analyze' => false,
                'thresholds' => [
                    'query_time' => 50,
                    'memory_usage' => 25,
                ],
                'checks' => [
                    'n_plus_one' => true,
                    'slow_queries' => true,
                    'duplicate_queries' => true,
                ],
            ],
        ],
    ],
    'db' => [
        'driver' => 'Pdo_Sqlite',
        'database' => ':memory:',
    ],
];
