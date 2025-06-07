<?php
/**
 * Laminas Microscope Configuration - PHP format.
 *
 * Save this file as config/autoload/laminas-microscope.local.php
 * or config/autoload/laminas-microscope.global.php in your application.
 */

return [
    'laminas_microscope' => [
        // Global settings
        'enabled' => true,
        'environment' => 'development', // development, staging, production

        // Storage settings
        'storage' => [
            'path' => '/tmp/laminas-microscope', // Ensure this path is writable by your web server
            'retention_days' => 30,
        ],

        // Component configuration
        'components' => [
            'whoops' => [
                'enabled' => true,
                'show_in_production' => false, // Highly recommended to keep false in production
                'editor' => 'vscode', // vscode, phpstorm, sublime, atom
                'page_title' => 'Application Error',
                // 'handlers' => ['pretty', 'json'], // Default handlers
            ],

            'debug_bar' => [
                'enabled' => true,
                'position' => 'bottom', // bottom, top
                'max_queries' => 100,
                'collectors' => [
                    'time',
                    'memory',
                    'exceptions',
                    'pdo', // Requires Laminas DB adapter with profiler enabled
                    'request',
                    'config',
                    'messages',
                    // Add other collectors if needed
                ],
                // 'show_in_production' => false, // Default is false

                // Update the base_url configuration option to match the asset serving route
                'base_url' => '/_debug/debugbar/resources', // Corrected public path for debug bar assets
            ],

            'microscope' => [
                'enabled' => true,
                'auto_analyze' => true, // Run analysis automatically after each request
                'checks' => [
                    'n_plus_one' => true,
                    'unused_routes' => true,
                    'unused_views' => true,
                    'slow_queries' => true,
                    'large_responses' => true,
                    'duplicate_queries' => true,
                ],
                'thresholds' => [
                    'query_time' => 100, // milliseconds
                    'response_size' => 1048576, // bytes (1MB)
                    'duplicate_query_threshold' => 3, // Number of times a query must be repeated to be flagged
                ],
                'reporting' => [
                    'log_level' => 'warning', // Log issues at this level or higher
                    'email_alerts' => false,
                    'webhook_url' => null,
                ],
                'analysis' => [
                    'store_reports' => true, // Store analysis reports on disk
                    'retention_days' => 30, // Keep reports for this many days
                ],
            ],
        ],

        // Database profiling settings (if using Laminas DB)
        'database' => [
            'log_queries' => true, // Enable query logging for Debug Bar/Microscope
            'explain_queries' => true, // Run EXPLAIN on slow queries
            'highlight_slow_queries' => true, // Highlight slow queries in Debug Bar
        ],

        // Security settings (recommended for non-development environments)
        // 'ip_whitelist' => [], // Uncomment and add IPs to restrict access
        // Example:
        // 'ip_whitelist' => [
        //     '127.0.0.1',
        //     '::1',
        //     'YOUR_PUBLIC_IP_ADDRESS', // Replace with your actual public IP
        // ],

        // Performance settings (optional)
        // 'cache_enabled' => true,
        // 'cache_ttl' => 3600,
    ],
];
