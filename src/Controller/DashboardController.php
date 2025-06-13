<?php

declare(strict_types=1);

namespace LaminasMicroscope\Controller;

use Exception;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use LaminasMicroscope\Cache\CacheManager;
use LaminasMicroscope\Collector\EnhancedPDOCollector;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Microscope\Storage\ReportStorage;
use RuntimeException;

use function date;
use function dirname;
use function file_exists;
use function file_get_contents;
use function ini_get;
use function is_writable;
use function json_encode;
use function memory_get_peak_usage;
use function memory_get_usage;
use function pathinfo;
use function strpos;

use const JSON_PRETTY_PRINT;
use const PATHINFO_EXTENSION;
use const PHP_VERSION;

class DashboardController extends AbstractActionController
{
    private ComponentManager $componentManager;
    private ConfigurationService $config;
    private ?ReportStorage $reportStorage;
    private ?CacheManager $cacheManager;
    private ?EnhancedPDOCollector $enhancedPDOCollector;

    public function __construct(
        ComponentManager $componentManager,
        ConfigurationService $config,
        ?ReportStorage $reportStorage = null,
        ?CacheManager $cacheManager = null,
        ?EnhancedPDOCollector $enhancedPDOCollector = null
    ) {
        $this->componentManager     = $componentManager;
        $this->config               = $config;
        $this->reportStorage        = $reportStorage;
        $this->cacheManager         = $cacheManager;
        $this->enhancedPDOCollector = $enhancedPDOCollector;
    }

    /**
     * Main dashboard page
     */
    public function indexAction(): ViewModel
    {
        $viewModel = new ViewModel([
            'componentManager' => $this->componentManager,
            'config'           => $this->config,
            'recentReports'    => $this->getRecentReports(),
            'systemInfo'       => $this->getSystemInfo(),
        ]);

        $viewModel->setTemplate('laminas-microscope/index');
        return $viewModel;
    }

    /**
     * API endpoints for dashboard
     */
    public function apiAction()
    {
        $request = $this->getRequest();
        $action  = $this->params()->fromRoute('action', 'status');

        if (! $request->isPost() && ! $request->isGet()) {
            return $this->getResponse()->setStatusCode(405);
        }

        switch ($action) {
            case 'status':
                return $this->jsonResponse([
                    'success' => true,
                    'data'    => [
                        'components'  => [
                            'whoops'     => $this->componentManager->isEnabled('whoops'),
                            'debug_bar'  => $this->componentManager->isEnabled('debug_bar'),
                            'microscope' => $this->componentManager->isEnabled('microscope'),
                        ],
                        'environment' => $this->config->get('laminas_microscope.environment', 'unknown'),
                        'debug_mode'  => $this->componentManager->isEnabled(),
                    ],
                ]);

            case 'clear-reports':
                if (! $request->isPost()) {
                    return $this->getResponse()->setStatusCode(405);
                }

                try {
                    $this->clearAnalysisReports();
                    return $this->jsonResponse(['success' => true]);
                } catch (Exception $e) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ], 500);
                }

            case 'export':
                try {
                    $data     = $this->exportDebugData();
                    $response = $this->getResponse();
                    $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
                    $response->getHeaders()->addHeaderLine('Content-Disposition', 'attachment; filename="debug-export-' . date('Y-m-d-H-i-s') . '.json"');
                    $response->setContent(json_encode($data, JSON_PRETTY_PRINT));
                    return $response;
                } catch (Exception $e) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ], 500);
                }

            default:
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Unknown action',
                ], 404);
        }
    }

    /**
     * Action to serve DebugBar assets.
     */
    public function assetsAction(): Response
    {
        $response = $this->getResponse();
        $file     = $this->params()->fromRoute('file');

        if (! $file) {
            $response->setStatusCode(404);
            return $response;
        }

        // Construct the full path to the asset file within the vendor directory
        // This assumes the standard composer vendor path and the module is 5 levels deep
        // from the application root (vendor/icw-kb/laminas-microscope/src/Controller)
        $assetPath = dirname(__DIR__, 5) . '/vendor/maximebf/debugbar/src/DebugBar/Resources/' . $file;

        // Basic security check: prevent directory traversal
        if (strpos($file, '..') !== false || ! file_exists($assetPath)) {
            $response->setStatusCode(404);
            return $response;
        }

        $extension   = pathinfo($assetPath, PATHINFO_EXTENSION);
        $contentType = match ($extension) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'woff', 'woff2' => 'font/woff2', // Use woff2 for both woff and woff2
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream', // Fallback
        };

        $content = file_get_contents($assetPath);

        if ($content === false) {
            $response->setStatusCode(500);
            return $response;
        }

        $response->getHeaders()->addHeaderLine('Content-Type', $contentType);
        $response->setContent($content);

        return $response;
    }

    /**
     * Get recent reports for dashboard
     */
    private function getRecentReports(): array
    {
        try {
            if ($this->reportStorage !== null) {
                return $this->reportStorage->loadRecentReports(5);
            }
            return [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get system information
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version'        => PHP_VERSION,
            'memory_limit'       => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'current_memory'     => memory_get_usage(true),
            'peak_memory'        => memory_get_peak_usage(true),
            'environment'        => $this->config->get('laminas_microscope.environment', 'unknown'),
            'storage_path'       => $this->config->get('laminas_microscope.storage.path', '/tmp/laminas-microscope'),
            'storage_writable'   => is_writable(dirname($this->config->get('laminas_microscope.storage.path', '/tmp/laminas-microscope'))),
        ];
    }

    /**
     * Clear analysis reports
     */
    private function clearAnalysisReports(): void
    {
        try {
            if ($this->reportStorage !== null) {
                $this->reportStorage->clearReports();
            }
        } catch (RuntimeException $e) {
            throw new RuntimeException('Failed to clear reports: ' . $e->getMessage());
        }
    }

    /**
     * Export debug data
     */
    private function exportDebugData(): array
    {
        return [
            'export_date'      => date('Y-m-d H:i:s'),
            'system_info'      => $this->getSystemInfo(),
            'component_status' => [
                'whoops'     => $this->componentManager->isEnabled('whoops'),
                'debug_bar'  => $this->componentManager->isEnabled('debug_bar'),
                'microscope' => $this->componentManager->isEnabled('microscope'),
            ],
            'configuration'    => $this->config->toArray(),
            'recent_reports'   => $this->getRecentReports(),
        ];
    }

    /**
     * Analytics dashboard action - Phase 3 feature
     */
    public function analyticsAction(): ViewModel
    {
        try {
            // Use injected service or create fallback
            $reportStorage = $this->reportStorage ?? new ReportStorage($this->config);

            $analytics = [
                'query_analysis'   => $reportStorage->getQueryAnalysis(),
                'route_analysis'   => $reportStorage->getRouteAnalysis(),
                'performance_data' => $reportStorage->getPerformanceData(),
                'summary'          => $reportStorage->getSummary(),
            ];

            return new ViewModel([
                'analytics' => $analytics,
                'config'    => $this->config,
            ]);
        } catch (Exception $e) {
            // Return empty analytics data on error
            $emptyAnalytics = [
                'query_analysis'   => [
                    'total_queries'       => 0,
                    'slow_queries'        => [],
                    'duplicate_queries'   => [],
                    'n_plus_one_patterns' => [],
                ],
                'route_analysis'   => [
                    'total_requests' => 0,
                    'popular_routes' => [],
                    'slow_routes'    => [],
                ],
                'performance_data' => [
                    'total_requests'        => 0,
                    'average_response_time' => 0,
                ],
                'summary'          => [
                    'performance_score' => 100,
                    'recommendations'   => [],
                ],
            ];

            return new ViewModel([
                'analytics' => $emptyAnalytics,
                'config'    => $this->config,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cache management action - Phase 3 feature
     */
    public function cacheAction(): ViewModel
    {
        $action = $this->params()->fromRoute('action', 'index');

        try {
            // Use injected service or create fallback
            $cacheManager = $this->cacheManager ?? new CacheManager($this->config);

            if ($action === 'clear' && $this->getRequest()->isPost()) {
                $category = $this->params()->fromPost('category', 'default');
                $cacheManager->flush($category);
                $this->flashMessenger()->addSuccessMessage("Cache cleared for category: {$category}");
                return $this->redirect()->toRoute('laminas-microscope/cache');
            }

            return new ViewModel([
                'cacheStats' => $cacheManager->getStats(),
                'config'     => $this->config,
            ]);
        } catch (Exception $e) {
            // Return empty cache stats on error
            return new ViewModel([
                'cacheStats' => ['default' => ['type' => 'FileAdapter', 'stats' => ['hits' => 0, 'misses' => 0, 'writes' => 0, 'deletes' => 0]]],
                'config'     => $this->config,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Performance monitoring action - Phase 3 feature
     */
    public function performanceAction(): ViewModel
    {
        try {
            if ($this->enhancedPDOCollector !== null) {
                $performanceData = $this->enhancedPDOCollector->collect();
            } else {
                // Create basic performance data if service not available
                $performanceData = [
                    'performance_score'        => 100,
                    'nb_statements'            => 0,
                    'nb_slow_statements'       => 0,
                    'nb_duplicate_statements'  => 0,
                    'accumulated_duration_str' => '0ms',
                    'analysis'                 => [
                        'n_plus_one_patterns' => [],
                        'query_types'         => [],
                    ],
                    'recommendations'          => [],
                    'statements'               => [],
                ];
            }

            return new ViewModel([
                'performanceData' => $performanceData,
                'config'          => $this->config,
            ]);
        } catch (Exception $e) {
            // Return empty performance data on error
            $emptyPerformanceData = [
                'performance_score'        => 100,
                'nb_statements'            => 0,
                'nb_slow_statements'       => 0,
                'nb_duplicate_statements'  => 0,
                'accumulated_duration_str' => '0ms',
                'analysis'                 => [
                    'n_plus_one_patterns' => [],
                    'query_types'         => [],
                ],
                'recommendations'          => [],
                'statements'               => [],
            ];

            return new ViewModel([
                'performanceData' => $emptyPerformanceData,
                'config'          => $this->config,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Helper method to return JSON responses
     */
    private function jsonResponse(array $data, int $statusCode = 200): Response
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
        $response->setContent(json_encode($data));
        return $response;
    }
}
