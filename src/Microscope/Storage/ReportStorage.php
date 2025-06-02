<?php

declare(strict_types=1);

namespace LaminasMicroscope\Microscope\Storage;

use LaminasMicroscope\Config\ConfigurationService;
use RecursiveIteratorIterator; // Corrected namespace
use RecursiveDirectoryIterator; // Corrected namespace
use Exception; // Corrected namespace

/**
 * Storage handler for microscope reports and analysis data
 */
class ReportStorage
{
    private string $storagePath;
    private ConfigurationService $configService;

    public function __construct(
        ConfigurationService $configService
    ) {
        $this->configService = $configService;
        $this->storagePath = $this->configService->getStoragePath() . '/microscope';
        $this->ensureStorageDirectoryExists();
    }

    /**
     * Store a report
     */
    public function storeReport(array $report): bool
    {
        $filename = $this->generateReportFilename($report);
        $filepath = $this->storagePath . '/' . $filename;

        try {
            $data = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return file_put_contents($filepath, $data) !== false;
        } catch (Exception $e) { // Corrected namespace
            error_log("Failed to store microscope report: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get query analysis data
     */
    public function getQueryAnalysis(): array
    {
        $reports = $this->loadRecentReports();
        $analysis = [
            'total_queries' => 0,
            'slow_queries' => [],
            'duplicate_queries' => [],
            'n_plus_one_patterns' => [],
            'average_query_time' => 0,
            'query_distribution' => [],
        ];

        $totalTime = 0;
        $queryCount = 0;

        foreach ($reports as $report) {
            if (isset($report['queries'])) {
                $analysis['total_queries'] += count($report['queries']);

                foreach ($report['queries'] as $query) {
                    $queryCount++;
                    $totalTime += $query['execution_time'] ?? 0;
                }
            }

            if (isset($report['analysis']['slow_queries'])) {
                $analysis['slow_queries'] = array_merge(
                    $analysis['slow_queries'],
                    $report['analysis']['slow_queries']
                );
            }

            if (isset($report['analysis']['duplicate_queries'])) {
                $analysis['duplicate_queries'] = array_merge(
                    $analysis['duplicate_queries'],
                    $report['analysis']['duplicate_queries']
                );
            }

            if (isset($report['analysis']['n_plus_one_patterns'])) {
                $analysis['n_plus_one_patterns'] = array_merge(
                    $analysis['n_plus_one_patterns'],
                    $report['analysis']['n_plus_one_patterns']
                );
            }
        }

        $analysis['average_query_time'] = $queryCount > 0 ? $totalTime / $queryCount : 0;

        return $analysis;
    }

    /**
     * Get route analysis data
     */
    public function getRouteAnalysis(): array
    {
        $reports = $this->loadRecentReports();
        $analysis = [
            'total_requests' => 0,
            'route_hits' => [],
            'slow_routes' => [],
            'popular_routes' => [],
            'controller_distribution' => [],
        ];

        foreach ($reports as $report) {
            $analysis['total_requests']++;

            if (isset($report['routes'][0])) {
                $route = $report['routes'][0];
                $routeName = $route['route_name'] ?? 'unknown';
                $controller = $route['controller'] ?? 'unknown';

                // Count route hits
                $analysis['route_hits'][$routeName] = ($analysis['route_hits'][$routeName] ?? 0) + 1;

                // Count controller distribution
                $analysis['controller_distribution'][$controller] =
                    ($analysis['controller_distribution'][$controller] ?? 0) + 1;

                // Check for slow routes
                $totalTime = $report['performance']['total_time'] ?? 0;
                if ($totalTime > 1000) { // More than 1 second
                    $analysis['slow_routes'][] = [
                        'route' => $routeName,
                        'controller' => $controller,
                        'time' => $totalTime,
                        'url' => $report['url'] ?? '',
                        'timestamp' => $report['timestamp'] ?? 0,
                    ];
                }
            }
        }

        // Sort popular routes
        arsort($analysis['route_hits']);
        $analysis['popular_routes'] = array_slice($analysis['route_hits'], 0, 10, true);

        return $analysis;
    }

    /**
     * Get performance data
     */
    public function getPerformanceData(): array
    {
        $reports = $this->loadRecentReports();
        $analysis = [
            'total_requests' => 0,
            'average_response_time' => 0,
            'average_memory_usage' => 0,
            'peak_memory_usage' => 0,
            'slow_requests' => [],
            'memory_intensive_requests' => [],
            'performance_trends' => [],
        ];

        $totalTime = 0;
        $totalMemory = 0;
        $maxMemory = 0;

        foreach ($reports as $report) {
            $analysis['total_requests']++;

            if (isset($report['performance'])) {
                $perf = $report['performance'];

                $requestTime = $perf['total_time'] ?? 0;
                $requestMemory = $perf['memory_usage'] ?? 0;
                $peakMemory = $perf['peak_memory'] ?? 0;

                $totalTime += $requestTime;
                $totalMemory += $requestMemory;
                $maxMemory = max($maxMemory, $peakMemory);

                // Collect slow requests
                if ($requestTime > 1000) {
                    $analysis['slow_requests'][] = [
                        'url' => $report['url'] ?? '',
                        'time' => $requestTime,
                        'memory' => $requestMemory,
                        'timestamp' => $report['timestamp'] ?? 0,
                    ];
                }

                // Collect memory intensive requests
                if ($requestMemory > 50 * 1024 * 1024) { // 50MB
                    $analysis['memory_intensive_requests'][] = [
                        'url' => $report['url'] ?? '',
                        'memory' => $requestMemory,
                        'time' => $requestTime,
                        'timestamp' => $report['timestamp'] ?? 0,
                    ];
                }

                // Build performance trends (hourly buckets)
                $hour = date('Y-m-d H:00', $report['timestamp'] ?? time());
                if (!isset($analysis['performance_trends'][$hour])) {
                    $analysis['performance_trends'][$hour] = [
                        'requests' => 0,
                        'total_time' => 0,
                        'total_memory' => 0,
                    ];
                }
                $analysis['performance_trends'][$hour]['requests']++;
                $analysis['performance_trends'][$hour]['total_time'] += $requestTime;
                $analysis['performance_trends'][$hour]['total_memory'] += $requestMemory;
            }
        }

        $requestCount = $analysis['total_requests'];
        $analysis['average_response_time'] = $requestCount > 0 ? $totalTime / $requestCount : 0;
        $analysis['average_memory_usage'] = $requestCount > 0 ? $totalMemory / $requestCount : 0;
        $analysis['peak_memory_usage'] = $maxMemory;

        return $analysis;
    }

    /**
     * Get summary data
     */
    public function getSummary(): array
    {
        $reports = $this->loadRecentReports();
        $summary = [
            'total_reports' => count($reports),
            'date_range' => $this->getDateRange($reports),
            'issues_detected' => 0,
            'performance_score' => 100,
            'recommendations' => [],
        ];

        $issuesCount = 0;
        $performanceIssues = 0;

        foreach ($reports as $report) {
            if (isset($report['analysis'])) {
                $analysis = $report['analysis'];

                if ($analysis['n_plus_one_detected'] ?? false) {
                    $issuesCount++;
                }

                if (!empty($analysis['slow_queries'])) {
                    $issuesCount += count($analysis['slow_queries']);
                }

                if (!empty($analysis['duplicate_queries'])) {
                    $issuesCount += count($analysis['duplicate_queries']);
                }

                if ($analysis['large_response'] ?? false) {
                    $performanceIssues++;
                }
            }

            if (isset($report['performance']['total_time']) && $report['performance']['total_time'] > 1000) {
                $performanceIssues++;
            }
        }

        $summary['issues_detected'] = $issuesCount;

        // Calculate performance score (0-100)
        $totalRequests = count($reports);
        if ($totalRequests > 0) {
            $issueRatio = ($issuesCount + $performanceIssues) / $totalRequests;
            $summary['performance_score'] = max(0, 100 - ($issueRatio * 100));
        }

        // Generate recommendations
        if ($issuesCount > 0) {
            $summary['recommendations'][] = "Consider optimizing database queries to reduce N+1 patterns and slow queries.";
        }

        if ($performanceIssues > 0) {
            $summary['recommendations'][] = "Review memory usage and response times to improve performance.";
        }

        return $summary;
    }

    /**
     * Load recent reports
     */
    public function loadRecentReports(int $limit = 100): array
    {
        if (!is_dir($this->storagePath)) {
            return [];
        }

        $files = glob($this->storagePath . '/report_*.json');
        if (empty($files)) {
            return [];
        }

        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $reports = [];
        $count = 0;

        foreach ($files as $file) {
            if ($count >= $limit) {
                break;
            }

            $content = file_get_contents($file);
            if ($content !== false) {
                $report = json_decode($content, true);
                if ($report !== null) {
                    $reports[] = $report;
                    $count++;
                }
            }
        }

        return $reports;
    }

    /**
     * Generate filename for report
     */
    private function generateReportFilename(array $report): string
    {
        $timestamp = $report['timestamp'] ?? microtime(true);
        $date = date('Y-m-d_H-i-s', (int) $timestamp);
        $microseconds = sprintf('%06d', ($timestamp - floor($timestamp)) * 1000000);

        return "report_{$date}_{$microseconds}.json";
    }

    /**
     * Ensure storage directory exists
     */
    private function ensureStorageDirectoryExists(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Get date range from reports
     */
    private function getDateRange(array $reports): array
    {
        if (empty($reports)) {
            return ['start' => null, 'end' => null];
        }

        $timestamps = array_column($reports, 'timestamp');
        $timestamps = array_filter($timestamps);

        if (empty($timestamps)) {
            return ['start' => null, 'end' => null];
        }

        return [
            'start' => date('Y-m-d H:i:s', min($timestamps)),
            'end' => date('Y-m-d H:i:s', max($timestamps)),
        ];
    }

    /**
     * Clean old reports based on retention policy
     */
    public function cleanOldReports(): int
    {
        $retentionDays = $this->configService->getRetentionDays();
        $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);

        $files = glob($this->storagePath . '/report_*.json');
        $deletedCount = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Clear all reports
     */
    public function clearReports(): void
    {
        if (!is_dir($this->storagePath)) {
            return;
        }

        $files = new RecursiveIteratorIterator( // Corrected namespace
            new RecursiveDirectoryIterator($this->storagePath, RecursiveDirectoryIterator::SKIP_DOTS), // Corrected namespace
            RecursiveIteratorIterator::CHILD_FIRST // Corrected namespace
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        // Optionally remove the directory itself if it's empty
        if (is_dir($this->storagePath) && count(scandir($this->storagePath)) <= 2) {
             rmdir($this->storagePath);
        }
    }
}
