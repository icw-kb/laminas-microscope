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
            'collectors' => [
                'time',
                'memory',
                'exceptions',
                'pdo',
                'request',
                'config',
                'messages',
            ],
            'debug_bar' => [
                'enabled' => true,
                'position' => 'bottom',
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
        'collector_mapping' => [
            'time'     => [
                'class'   => \DebugBar\DataCollector\TimeDataCollector::class,
                'factory' => 'direct',
            ],
            'memory'   => [
                'class'   => \DebugBar\DataCollector\MemoryCollector::class,
                'factory' => 'direct',
            ],
            'messages' => [
                'class'   => \DebugBar\DataCollector\MessagesCollector::class,
                'factory' => 'direct',
            ],
            'phpinfo'  => [
                'class'   => \DebugBar\DataCollector\PhpInfoCollector::class,
                'factory' => 'direct',
            ],
            'php'      => [
                'class'   => \DebugBar\DataCollector\PhpInfoCollector::class,
                'factory' => 'direct',
            ],
            'config'   => [
                'class'        => \LaminasMicroscope\DebugBar\Collectors\LaminasConfigCollector::class,
                'factory'      => 'service',
                'service_name' => \LaminasMicroscope\DebugBar\Collectors\LaminasConfigCollector::class,
            ],
            'pdo'      => [
                'class'        => \LaminasMicroscope\DebugBar\Collectors\PDOCollector::class,
                'factory'      => 'service',
                'service_name' => \LaminasMicroscope\DebugBar\Collectors\PDOCollector::class,
            ],
            'request'  => [
                'class'        => \LaminasMicroscope\DebugBar\Collectors\LaminasRequestCollector::class,
                'factory'      => 'service',
                'service_name' => \LaminasMicroscope\DebugBar\Collectors\LaminasRequestCollector::class,
            ],
        ],
    ],
    'db' => [
        'driver' => 'Pdo_Sqlite',
        'database' => ':memory:',
    ],
];
