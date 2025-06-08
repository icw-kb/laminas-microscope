<?php

return [
    'laminas_microscope' => [
        'enabled' => true,
        'environment' => 'development',
        'collectors' => [
            'time',
            'memory',
            'exceptions',
            'pdo',
            'request',
            'config',
        ],
        'storage' => [
            'path' => '/tmp/laminas-microscope',
        ],
        'components' => [
            'whoops' => [
                'enabled' => true,
                'show_in_production' => false,
                'editor' => 'vscode',
                'page_title' => 'Application Error',
            ],
            'debug_bar' => [
                'enabled' => true,
                'position' => 'bottom',
                'max_queries' => 100,
                'collectors_only' => false,
            ],
            'microscope' => [
                'enabled' => true,
                'auto_analyze' => true,
                'checks' => [
                    'n_plus_one' => true,
                    'unused_routes' => true,
                    'unused_views' => true,
                    'slow_queries' => true,
                    'large_responses' => true,
                    'duplicate_queries' => true,
                ],
                'thresholds' => [
                    'query_time' => 100,
                    'response_size' => 1048576,
                    'duplicate_query_threshold' => 3,
                ],
                'reporting' => [
                    'log_level' => 'warning',
                    'email_alerts' => false,
                    'webhook_url' => null,
                ],
                'analysis' => [
                    'store_reports' => true,
                    'retention_days' => 30,
                ],
            ],
        ],
        'database' => [
            'log_queries' => true,
            'explain_queries' => true,
            'highlight_slow_queries' => true,
        ],
        'ip_whitelist' => [],
        'cache_enabled' => true,
        'cache_ttl' => 3600,
    ],
];
