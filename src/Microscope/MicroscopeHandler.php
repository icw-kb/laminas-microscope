<?php

declare(strict_types=1);

namespace LaminasMicroscope\Microscope;

use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Microscope\Storage\ReportStorage; // Corrected namespace
use Laminas\Mvc\MvcEvent; // Corrected namespace
use Psr\Container\ContainerInterface; // Corrected namespace
use Exception; // Corrected namespace
use RuntimeException; // Corrected namespace
use Laminas\Router\RouteMatch; // Added use statement

/**
 * MicroscopeHandler - Advanced profiling and analysis for Laminas applications
 */
class MicroscopeHandler
{
    private ComponentManager $componentManager;
    private ConfigurationService $configService;
    private ContainerInterface $container;
    private ReportStorage $storage;
    private array $profileData = [];
    private float $startTime;
    private int $startMemory;

    public function __construct(
        ComponentManager $componentManager,
        ConfigurationService $configService,
        ContainerInterface $container
    ) {
        $this->componentManager = $componentManager;
        $this->configService = $configService;
        $this->container = $container;
    }

    /**
     * Check if microscope is enabled
     */
    public function isEnabled(): bool
    {
        return $this->componentManager->isEnabled('microscope');
    }

    /**
     * Get component name
     */
    public function getName(): string
    {
        return 'microscope';
    }

    /**
     * Get component configuration
     */
    public function getConfig(): array
    {
        return $this->configService->get('laminas_microscope.components.microscope', []);
    }

    /**
     * Initialize the microscope handler
     */
    public function initialize(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        // Initialize storage - pass the ConfigurationService object, not just the path
        $this->storage = new ReportStorage($this->configService);

        // Initialize profiling data structure
        $this->profileData = [
            'queries' => [],
            'routes' => [],
            'views' => [],
            'performance' => [],
            'analysis' => [],
        ];

        // Set start time and memory
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * Start profiling for an MVC event
     */
    public static function startProfiling(MvcEvent $event): void // Corrected namespace and made static for listener
    {
        // Get the MicroscopeHandler instance from the ServiceManager
        $serviceManager = $event->getApplication()->getServiceManager();
        $handler = $serviceManager->get(self::class);

        if (!$handler->isEnabled()) {
            return;
        }

        // Ensure initialization
        if (!isset($handler->startTime)) {
            $handler->initialize();
        }

        $routeMatch = $event->getRouteMatch();
        if ($routeMatch instanceof RouteMatch) { // Added type check
            $handler->profileData['routes'][] = [
                'route_name' => $routeMatch->getMatchedRouteName(),
                'controller' => $routeMatch->getParam('controller'),
                'action' => $routeMatch->getParam('action'),
                'params' => $routeMatch->getParams(),
                'timestamp' => microtime(true),
            ];
        }
    }

    /**
     * Profile dispatch completion
     */
    public static function profileDispatch(MvcEvent $event): void // Corrected namespace and made static for listener
    {
        // Get the MicroscopeHandler instance from the ServiceManager
        $serviceManager = $event->getApplication()->getServiceManager();
        $handler = $serviceManager->get(self::class);

        if (!$handler->isEnabled()) {
            return;
        }

        // Ensure we have start time
        if (!isset($handler->startTime)) {
            $handler->initialize();
        }

        // Get memory usage safely
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        // Calculate memory usage difference (ensure positive values)
        $memoryUsed = max(0, $currentMemory - $handler->startMemory);

        // Record performance metrics
        $handler->profileData['performance'] = [
            'total_time' => (microtime(true) - $handler->startTime) * 1000, // Convert to milliseconds
            'memory_usage' => $handler->formatBytes($memoryUsed),
            'peak_memory' => $handler->formatBytes($peakMemory),
            'memory_usage_bytes' => $memoryUsed,
            'peak_memory_bytes' => $peakMemory,
        ];

        // Run analysis if auto-analyze is enabled
        $config = $handler->getConfig();
        if ($config['auto_analyze'] ?? false) {
            $handler->performAnalysis();
        }
    }

    /**
     * Get current profile data
     */
    public function getProfileData(): array
    {
        return $this->profileData;
    }

    /**
     * Run comprehensive analysis
     */
    public function runAnalysis(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $analysis = [
            'id' => uniqid('analysis_', true),
            'created_at' => date('Y-m-d H:i:s'),
            'queries' => $this->profileData['queries'] ?? [],
            'performance' => $this->profileData['performance'] ?? [],
            'issues' => [],
            'performance_score' => 100,
        ];

        // Analyze queries for issues
        $config = $this->getConfig();
        $checks = $config['checks'] ?? [];

        if ($checks['duplicate_queries'] ?? true) {
            $duplicates = $this->findDuplicateQueries();
            if (!empty($duplicates)) {
                $analysis['issues'][] = [
                    'type' => 'duplicate_queries',
                    'severity' => 'warning',
                    'message' => 'Found ' . count($duplicates) . ' duplicate queries',
                    'data' => $duplicates,
                ];
                $analysis['performance_score'] -= 10;
            }
        }

        if ($checks['slow_queries'] ?? true) {
            $slowQueries = $this->findSlowQueries();
            if (!empty($slowQueries)) {
                $analysis['issues'][] = [
                    'type' => 'slow_queries',
                    'severity' => 'warning',
                    'message' => 'Found ' . count($slowQueries) . ' slow queries',
                    'data' => $slowQueries,
                ];
                $analysis['performance_score'] -= 15;
            }
        }

        // Analyze performance metrics
        $performance = $this->profileData['performance'] ?? [];
        if (isset($performance['total_time']) && $performance['total_time'] > 2000) { // 2 seconds
            $analysis['issues'][] = [
                'type' => 'slow_response',
                'severity' => 'warning',
                'message' => 'Response time exceeded 2 seconds',
                'data' => ['response_time' => $performance['total_time']],
            ];
            $analysis['performance_score'] -= 20;
        }

        $analysis['performance_score'] = max(0, $analysis['performance_score']);

        return $analysis;
    }

    /**
     * Record a database query for analysis
     */
    public function recordQuery(array $queryData): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->profileData['queries'][] = array_merge($queryData, [
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Record a view rendering
     */
    public function recordView(string $template, float $renderTime): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->profileData['views'][] = [
            'template' => $template,
            'render_time' => $renderTime,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Perform internal analysis
     */
    private function performAnalysis(): void
    {
        $analysis = $this->runAnalysis();
        $this->profileData['analysis'] = $analysis;

        // Log critical issues
        foreach ($analysis['issues'] as $issue) {
            if ($issue['severity'] === 'critical') {
                error_log('Laminas Microscope Critical Issue: ' . $issue['message']);
            }
        }
    }

    /**
     * Find duplicate queries
     */
    private function findDuplicateQueries(): array
    {
        $queries = $this->profileData['queries'] ?? [];
        $queryMap = [];
        $duplicates = [];

        foreach ($queries as $query) {
            $sql = $query['sql'] ?? '';
            $key = md5($sql);

            if (!isset($queryMap[$key])) {
                $queryMap[$key] = [
                    'sql' => $sql,
                    'count' => 0,
                    'total_time' => 0,
                ];
            }

            $queryMap[$key]['count']++;
            $queryMap[$key]['total_time'] += $query['duration'] ?? 0;
        }

        $threshold = $this->configService->get('laminas_microscope.components.microscope.thresholds.duplicate_query_threshold', 3);

        foreach ($queryMap as $queryInfo) {
            if ($queryInfo['count'] >= $threshold) {
                $duplicates[] = $queryInfo;
            }
        }

        return $duplicates;
    }

    /**
     * Find slow queries
     */
    private function findSlowQueries(): array
    {
        $queries = $this->profileData['queries'] ?? [];
        $slowQueries = [];
        $threshold = $this->configService->get('laminas_microscope.components.microscope.thresholds.query_time', 100);

        foreach ($queries as $query) {
            if (($query['duration'] ?? 0) > $threshold) {
                $slowQueries[] = $query;
            }
        }

        return $slowQueries;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = log($bytes, 1024);
        $index = floor($base);

        return round(pow(1024, $base - $index), 2) . ' ' . $units[$index];
    }

    /**
     * Get recent reports from storage
     */
    public function getRecentReports(int $limit = 100): array
    {
        if (!$this->isEnabled()) {
            return [];
         }
         // Ensure storage is initialized
        if (!isset($this->storage)) {
             $this->initialize();
        }
        return $this->storage->loadRecentReports($limit);
    }

    /**
     * Clear all reports from storage
     */
    public function clearReports(): void
    {
         if (!$this->isEnabled()) {
            return;
        }
         // Ensure storage is initialized
        if (!isset($this->storage)) {
             $this->initialize();
        }
        $this->storage->clearReports();
    }
}
