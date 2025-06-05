<?php

declare(strict_types=1);

namespace LaminasMicroscope\Microscope;

use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Microscope\Storage\ReportStorage;
use Laminas\Mvc\MvcEvent;
use Psr\Container\ContainerInterface;
use Exception;
use RuntimeException;
use Laminas\Router\RouteMatch;

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
    private float $requestStartTime; // Overall request start time
    private int $startMemory; // Memory at request start
    private array $eventTimestamps = []; // Timestamps for specific events

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

        // Set start time and memory for the overall request
        $this->requestStartTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->eventTimestamps = []; // Reset timestamps
        $this->eventTimestamps['request_start'] = $this->requestStartTime; // Record overall request start
    }

    /**
     * Start profiling for an MVC event (records route details and route_start timestamp)
     */
    public static function startProfiling(MvcEvent $event): void
    {
        // Get the MicroscopeHandler instance from the ServiceManager
        $serviceManager = $event->getApplication()->getServiceManager();
        $handler = $serviceManager->get(self::class);

        if (!$handler->isEnabled()) {
            return;
        }

        // Ensure initialization
        if (!isset($handler->requestStartTime)) {
            $handler->initialize();
        }

        // Record route details
        $routeMatch = $event->getRouteMatch();
        if ($routeMatch instanceof RouteMatch) {
            $handler->profileData['routes'][] = [
                'route_name' => $routeMatch->getMatchedRouteName(),
                'controller' => $routeMatch->getParam('controller'),
                'action' => $routeMatch->getParam('action'),
                'params' => $routeMatch->getParams(),
                'timestamp' => microtime(true),
            ];
        }

        // Record route start timestamp
        $handler->eventTimestamps['route_start'] = microtime(true);
    }

    /**
     * Profile dispatch completion (records dispatch_end timestamp)
     */
    public static function profileDispatch(MvcEvent $event): void
    {
        // Get the MicroscopeHandler instance from the ServiceManager
        $serviceManager = $event->getApplication()->getServiceManager();
        $handler = $serviceManager->get(self::class);

        if (!$handler->isEnabled()) {
            return;
        }

        // Ensure we have start time
        if (!isset($handler->requestStartTime)) {
            $handler->initialize();
        }

        // Record dispatch end timestamp
        $handler->eventTimestamps['dispatch_end'] = microtime(true);

        // Note: Final performance metrics and analysis are now calculated in finalizeProfiling
    }

    /**
     * Record render start timestamp
     */
    public static function startRender(MvcEvent $event): void
    {
        $serviceManager = $event->getApplication()->getServiceManager();
        $handler = $serviceManager->get(self::class);
        if (!$handler->isEnabled()) { return; }
        $handler->eventTimestamps['render_start'] = microtime(true);
    }

    /**
     * Record render end timestamp
     */
    public static function stopRender(MvcEvent $event): void
    {
        $serviceManager = $event->getApplication()->getServiceManager();
        $handler = $serviceManager->get(self::class);
        if (!$handler->isEnabled()) { return; }
        $handler->eventTimestamps['render_end'] = microtime(true);
    }

    /**
     * Record request end timestamp and finalize performance data
     */
    public static function finalizeProfiling(MvcEvent $event): void
    {
        $serviceManager = $event->getApplication()->getServiceManager();
        $handler = $serviceManager->get(self::class);
        if (!$handler->isEnabled()) { return; }

        $handler->eventTimestamps['request_end'] = microtime(true);

        // Now, calculate final performance metrics and breakdown
        $handler->calculateFinalPerformanceMetrics();

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

        // Ensure performance metrics are calculated before running analysis
        if (!isset($this->profileData['performance']['breakdown'])) {
             $this->calculateFinalPerformanceMetrics();
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

    /**
     * Calculate final performance metrics including breakdown.
     * This should be called at the end of the request lifecycle.
     */
    private function calculateFinalPerformanceMetrics(): void
    {
        $timestamps = $this->eventTimestamps;

        // Ensure request_end is set
        if (!isset($timestamps['request_end'])) {
             $timestamps['request_end'] = microtime(true);
        }

        $start = $timestamps['request_start'] ?? microtime(true);
        $end = $timestamps['request_end'];
        $totalTime = ($end - $start) * 1000; // Total request time in ms

        $memoryUsed = max(0, memory_get_usage(true) - $this->startMemory); // Memory used during request

        $breakdown = $this->calculateEventBreakdownFromTimestamps($timestamps);

        $this->profileData['performance'] = [
            'total_time' => $totalTime,
            'memory_usage' => $this->formatBytes($memoryUsed),
            'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
            'memory_usage_bytes' => $memoryUsed,
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'event_timestamps' => $timestamps, // Store raw timestamps
            'breakdown' => $breakdown, // Calculated breakdown
        ];
    }

    /**
     * Calculate duration for each phase from timestamps.
     * Assumes a general MVC event flow: Request -> Bootstrap -> Route -> Dispatch -> Render -> Finish
     */
    private function calculateEventBreakdownFromTimestamps(array $timestamps): array
    {
        $breakdown = [];

        $requestStart = $timestamps['request_start'] ?? microtime(true);
        $routeStart = $timestamps['route_start'] ?? $requestStart;
        $dispatchEnd = $timestamps['dispatch_end'] ?? $routeStart; // Dispatch end is often after route
        $renderStart = $timestamps['render_start'] ?? $dispatchEnd; // Render starts after dispatch
        $renderEnd = $timestamps['render_end'] ?? $renderStart; // Render ends before finish
        $requestEnd = $timestamps['request_end'] ?? $renderEnd; // Request ends at finish

        // Calculate durations based on sequence
        $breakdown['bootstrap'] = max(0, ($routeStart - $requestStart) * 1000);
        $breakdown['route'] = max(0, ($dispatchEnd - $routeStart) * 1000); // Duration from route start to dispatch end
        $breakdown['dispatch'] = max(0, ($renderStart - $dispatchEnd) * 1000); // Duration from dispatch end to render start
        $breakdown['render'] = max(0, ($requestEnd - $renderStart) * 1000); // Duration from render start to request end

        // Adjust render time if total calculated exceeds total request time (due to overlaps or missing events)
        $totalCalculated = array_sum($breakdown);
        $totalRequestTime = ($requestEnd - $requestStart) * 1000;

        if ($totalCalculated > $totalRequestTime + 1) { // Allow for minor floating point inaccuracies
             $excess = $totalCalculated - $totalRequestTime;
             $breakdown['render'] = max(0, $breakdown['render'] - $excess);
        }

        return $breakdown;
    }

    /**
     * Get the performance breakdown data.
     */
    public function getPerformanceBreakdown(): array
    {
        // Ensure performance data is calculated
        if (!isset($this->profileData['performance']['breakdown'])) {
             $this->calculateFinalPerformanceMetrics();
        }
        return $this->profileData['performance']['breakdown'] ?? [];
    }
}
