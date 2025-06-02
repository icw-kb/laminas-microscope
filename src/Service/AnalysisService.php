<?php

declare(strict_types=1);

namespace LaminasMicroscope\Service;

use LaminasMicroscope\Config\ConfigurationService;

/**
 * Service for analyzing application performance and behavior
 */
class AnalysisService
{
    public function __construct(
        private ConfigurationService $configService
    ) {}

    /**
     * Get current analysis data
     */
    public function getCurrentAnalysis(): array
    {
        return [
            'performance' => $this->getPerformanceMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'memory' => $this->getMemoryMetrics(),
            'routes' => $this->getRouteMetrics(),
            'timestamp' => time(),
        ];
    }

    /**
     * Get system overview
     */
    public function getSystemOverview(): array
    {
        return [
            'system' => [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'opcache_enabled' => extension_loaded('opcache'),
                'xdebug_enabled' => extension_loaded('xdebug'),
            ],
            'application' => [
                'environment' => $this->configService->getEnvironment(),
                'debug_mode' => $this->configService->isDebugMode(),
                'storage_path' => $this->configService->getStoragePath(),
                'components_enabled' => count(array_filter(
                    $this->configService->get('laminas_microscope.components', []),
                    fn($component) => $component['enabled'] ?? false
                )),
            ],
        ];
    }

    /**
     * Get profiler data for a specific session
     */
    public function getProfilerData(string $sessionId): array
    {
        return [
            'timeline' => $this->getTimelineData($sessionId),
            'queries' => $this->getQueryData($sessionId),
            'memory' => $this->getMemoryData($sessionId),
            'exceptions' => $this->getExceptionData($sessionId),
        ];
    }

    /**
     * Get detailed analysis data
     */
    public function getDetailedAnalysis(): array
    {
        return [
            'routes' => $this->getRouteAnalysis(),
            'controllers' => $this->getControllerAnalysis(),
            'services' => $this->getServiceAnalysis(),
            'database' => $this->getDatabaseAnalysis(),
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'included_files' => count(get_included_files()),
        ];
    }

    /**
     * Get database metrics
     */
    private function getDatabaseMetrics(): array
    {
        return [
            'queries_count' => 0, // Would be tracked by profiler
            'total_time' => 0.0,
            'slow_queries' => [],
            'duplicate_queries' => [],
        ];
    }

    /**
     * Get memory metrics
     */
    private function getMemoryMetrics(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'percentage' => round((memory_get_usage(true) / $this->parseBytes(ini_get('memory_limit'))) * 100, 2),
        ];
    }

    /**
     * Get route metrics
     */
    private function getRouteMetrics(): array
    {
        return [
            'matched_route' => $_SERVER['REQUEST_URI'] ?? '/',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'controller' => 'Unknown',
            'action' => 'Unknown',
        ];
    }

    /**
     * Get timeline data for profiler
     */
    private function getTimelineData(string $sessionId): array
    {
        return [
            ['event' => 'Request Start', 'time' => 0.0, 'memory' => memory_get_usage()],
            ['event' => 'Bootstrap', 'time' => 0.01, 'memory' => memory_get_usage()],
            ['event' => 'Routing', 'time' => 0.02, 'memory' => memory_get_usage()],
            ['event' => 'Controller', 'time' => 0.03, 'memory' => memory_get_usage()],
        ];
    }

    /**
     * Get query data for profiler
     */
    private function getQueryData(string $sessionId): array
    {
        return [
            ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [1], 'time' => 0.01],
            ['sql' => 'UPDATE users SET last_login = NOW() WHERE id = ?', 'params' => [1], 'time' => 0.005],
        ];
    }

    /**
     * Get memory data for profiler
     */
    private function getMemoryData(string $sessionId): array
    {
        return [
            'snapshots' => [
                ['time' => 0.0, 'usage' => 1024000],
                ['time' => 0.01, 'usage' => 1536000],
                ['time' => 0.02, 'usage' => 2048000],
            ],
        ];
    }

    /**
     * Get exception data for profiler
     */
    private function getExceptionData(string $sessionId): array
    {
        return [
            'exceptions' => [],
            'errors' => [],
            'warnings' => [],
        ];
    }

    /**
     * Get route analysis
     */
    private function getRouteAnalysis(): array
    {
        return [
            ['name' => 'home', 'path' => '/', 'hits' => 150, 'avg_time' => 0.05],
            ['name' => 'about', 'path' => '/about', 'hits' => 75, 'avg_time' => 0.03],
        ];
    }

    /**
     * Get controller analysis
     */
    private function getControllerAnalysis(): array
    {
        return [
            ['name' => 'IndexController', 'actions' => 3, 'total_hits' => 200],
            ['name' => 'UserController', 'actions' => 5, 'total_hits' => 150],
        ];
    }

    /**
     * Get service analysis
     */
    private function getServiceAnalysis(): array
    {
        return [
            ['name' => 'UserService', 'instantiations' => 15, 'memory' => 102400],
            ['name' => 'DatabaseService', 'instantiations' => 3, 'memory' => 51200],
        ];
    }

    /**
     * Get database analysis
     */
    private function getDatabaseAnalysis(): array
    {
        return [
            'total_queries' => 25,
            'slow_queries' => 2,
            'duplicate_queries' => 1,
            'avg_query_time' => 0.015,
        ];
    }

    /**
     * Parse bytes from string (like "128M")
     */
    private function parseBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;
        
        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
}
