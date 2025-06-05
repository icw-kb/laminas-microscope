<?php

declare(strict_types=1);

namespace LaminasMicroscope\Service;

use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Microscope\MicroscopeHandler; // Import MicroscopeHandler

/**
 * Service for analyzing application performance and behavior
 */
class AnalysisService
{
    private ConfigurationService $configService;
    private MicroscopeHandler $microscopeHandler; // Inject MicroscopeHandler

    public function __construct(
        ConfigurationService $configService,
        MicroscopeHandler $microscopeHandler // Inject MicroscopeHandler
    ) {
        $this->configService = $configService;
        $this->microscopeHandler = $microscopeHandler;
    }

    /**
     * Get current analysis data
     */
    public function getCurrentAnalysis(): array
    {
        // Get collected profile data from MicroscopeHandler
        $profileData = $this->microscopeHandler->getProfileData();

        // Now analyze this data
        return [
            'performance' => $this->analyzePerformance($profileData), // Analyze performance data
            'database' => $this->analyzeDatabase($profileData), // Analyze database data
            'memory' => $this->analyzeMemory($profileData), // Analyze memory data
            'routes' => $this->analyzeRoutes($profileData), // Analyze route data
            'issues' => $this->analyzeIssues($profileData), // Analyze issues
            'summary' => $this->getSummary($profileData), // Get summary data
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
     * Get profiler data for a specific session (Placeholder - needs implementation to load from storage)
     */
    public function getProfilerData(string $sessionId): array
    {
        // This method should ideally load a specific report by ID from storage
        // For now, return dummy data or data from the current request if session ID is 'current'
        if ($sessionId === 'current') {
             $profileData = $this->microscopeHandler->getProfileData();
             return [
                 'timeline' => $this->getTimelineDataFromProfile($profileData), // Use collected data
                 'queries' => $profileData['queries'] ?? [],
                 'memory' => $this->getMemoryDataFromProfile($profileData), // Use collected data
                 'exceptions' => [], // Need to collect exceptions
             ];
        }

        // Placeholder for loading from storage
        return [
            'timeline' => [],
            'queries' => [],
            'memory' => [],
            'exceptions' => [],
        ];
    }

    /**
     * Get detailed analysis data (Placeholder - needs implementation)
     */
    public function getDetailedAnalysis(): array
    {
        // This method should perform deeper analysis across multiple reports or complex checks
        // For now, return basic structure
        return [
            'routes' => [], // $this->getRouteAnalysis(), // Needs implementation
            'controllers' => [], // $this->getControllerAnalysis(), // Needs implementation
            'services' => [], // $this->getServiceAnalysis(), // Needs implementation
            'database' => [], // $this->getDatabaseAnalysis(), // Needs implementation
        ];
    }

    /**
     * Analyze performance data from profile data.
     */
    private function analyzePerformance(array $profileData): array
    {
        $performance = $profileData['performance'] ?? [];

        // Get the calculated breakdown directly from the MicroscopeHandler
        $breakdown = $this->microscopeHandler->getPerformanceBreakdown();

        // Merge breakdown with overall performance metrics
        return array_merge($performance, [
            'breakdown' => $breakdown,
            // Add other performance analysis here (e.g., slow requests)
        ]);
    }

    /**
     * Analyze database data from profile data.
     */
    private function analyzeDatabase(array $profileData): array
    {
        $queries = $profileData['queries'] ?? [];
        // Add database-specific analysis here (e.g., slow queries, duplicates, N+1)
        // Note: MicroscopeHandler already has methods for this, could potentially use them
        // or duplicate logic here if AnalysisService is the primary analysis engine.
        // For now, just return the raw queries.
        return $queries;
    }

    /**
     * Analyze memory data from profile data.
     */
    private function analyzeMemory(array $profileData): array
    {
        $performance = $profileData['performance'] ?? [];
        // Add memory-specific analysis here (e.g., memory intensive requests)
        // For now, return basic memory metrics.
        return [
            'current' => $performance['memory_usage_bytes'] ?? memory_get_usage(true),
            'peak' => $performance['peak_memory_bytes'] ?? memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'percentage' => round((($performance['memory_usage_bytes'] ?? memory_get_usage(true)) / $this->parseBytes(ini_get('memory_limit'))) * 100, 2),
        ];
    }

    /**
     * Analyze route data from profile data.
     */
    private function analyzeRoutes(array $profileData): array
    {
        $routes = $profileData['routes'] ?? [];
        // Add route-specific analysis here (e.g., route hits, slow routes)
        // For now, return the raw route data.
        return $routes;
    }

    /**
     * Analyze issues from profile data.
     */
    private function analyzeIssues(array $profileData): array
    {
        // Issues are currently calculated by MicroscopeHandler's runAnalysis if auto_analyze is true.
        // We can retrieve them from the profile data.
        return $profileData['analysis']['issues'] ?? [];
    }

    /**
     * Get summary data from profile data.
     */
    public function getSummary(array $profileData): array
    {
        // This method should generate a high-level summary based on the analysis results.
        // For now, return a basic summary.
        $issues = $profileData['analysis']['issues'] ?? [];
        $performanceScore = $profileData['analysis']['performance_score'] ?? 100;

        return [
            'total_reports' => 1, // Assuming current request is one report
            'date_range' => ['start' => date('Y-m-d H:i:s', $profileData['performance']['event_timestamps']['request_start'] ?? time()), 'end' => date('Y-m-d H:i:s', $profileData['performance']['event_timestamps']['request_end'] ?? time())],
            'issues_detected' => count($issues),
            'performance_score' => $performanceScore,
            'recommendations' => $profileData['analysis']['recommendations'] ?? [], // Assuming recommendations are part of analysis
        ];
    }


    /**
     * Get performance metrics (Deprecated - use analyzePerformance)
     */
    private function getPerformanceMetrics(): array
    {
        // This method is now deprecated as analysis is done on profile data
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => $_SERVER['REQUEST_TIME_FLOAT'] ? (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) : 0,
            'included_files' => count(get_included_files()),
        ];
    }

    /**
     * Get database metrics (Deprecated - use analyzeDatabase)
     */
    private function getDatabaseMetrics(): array
    {
        // This method is now deprecated
        return [
            'queries_count' => 0,
            'total_time' => 0.0,
            'slow_queries' => [],
            'duplicate_queries' => [],
        ];
    }

    /**
     * Get memory metrics (Deprecated - use analyzeMemory)
     */
    private function getMemoryMetrics(): array
    {
        // This method is now deprecated
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'percentage' => round((memory_get_usage(true) / $this->parseBytes(ini_get('memory_limit'))) * 100, 2),
        ];
    }

    /**
     * Get route metrics (Deprecated - use analyzeRoutes)
     */
    private function getRouteMetrics(): array
    {
        // This method is now deprecated
        return [
            'matched_route' => $_SERVER['REQUEST_URI'] ?? '/',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'controller' => 'Unknown',
            'action' => 'Unknown',
        ];
    }

    /**
     * Get timeline data from profile data.
     */
    private function getTimelineDataFromProfile(array $profileData): array
    {
        $timestamps = $profileData['event_timestamps'] ?? [];
        $timeline = [];

        // Convert timestamps to a timeline format
        foreach ($timestamps as $event => $time) {
            $timeline[] = ['event' => $event, 'time' => $time, 'memory' => memory_get_usage()]; // Memory at that point (approx)
        }

        // Sort timeline by time
        usort($timeline, fn($a, $b) => $a['time'] <=> $b['time']);

        return $timeline;
    }

    /**
     * Get memory data from profile data.
     */
    private function getMemoryDataFromProfile(array $profileData): array
    {
        $performance = $profileData['performance'] ?? [];
        return [
            'snapshots' => [
                 ['time' => 0.0, 'usage' => $performance['memory_usage_bytes'] ?? memory_get_usage(true)], // Simplified
            ],
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

        // Ensure non-negative durations
        foreach ($breakdown as $key => $duration) {
             $breakdown[$key] = max(0, $duration);
        }

        // Adjust render time if total calculated exceeds total request time (due to overlaps or missing events)
        $totalCalculated = array_sum($breakdown);
        $totalRequestTime = ($requestEnd - $requestStart) * 1000;

        if ($totalCalculated > $totalRequestTime + 1) { // Allow for minor floating point inaccuracies
             $excess = $totalCalculated - $totalRequestTime;
             $breakdown['render'] = max(0, $breakdown['render'] - $excess);
        }


        return $breakdown;
    }
}
