<?php

declare(strict_types=1);

namespace LaminasMicroscope\Controller; 

use Laminas\Mvc\Controller\AbstractActionController; 
use Laminas\View\Model\ViewModel; 
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;
use Laminas\Http\Response; 
use RuntimeException;
use Exception;
use LaminasMicroscope\Microscope\MicroscopeHandler;
use DebugBar\JavascriptRenderer; 

class DashboardController extends AbstractActionController
{
    private ComponentManager $componentManager;
    private ConfigurationService $config;

    public function __construct(
        ComponentManager $componentManager,
        ConfigurationService $config
    ) {
        $this->componentManager = $componentManager;
        $this->config = $config;
    }

    /**
     * Main dashboard page
     */
    public function indexAction(): ViewModel
    {
        $viewModel = new ViewModel([
            'componentManager' => $this->componentManager,
            'config' => $this->config,
            'recentReports' => $this->getRecentReports(),
            'systemInfo' => $this->getSystemInfo(),
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
        $action = $this->params()->fromRoute('action', 'status');

        if (!$request->isPost() && !$request->isGet()) {
            return $this->getResponse()->setStatusCode(405);
        }

        switch ($action) {
            case 'status':
                return $this->jsonResponse([
                    'success' => true,
                    'data' => [
                        'components' => [
                            'whoops' => $this->componentManager->isEnabled('whoops'),
                            'debug_bar' => $this->componentManager->isEnabled('debug_bar'),
                            'microscope' => $this->componentManager->isEnabled('microscope'),
                        ],
                        'environment' => $this->config->get('laminas_microscope.environment', 'unknown'),
                        'debug_mode' => $this->componentManager->isEnabled(),
                    ]
                ]);

            case 'clear-reports':
                if (!$request->isPost()) {
                    return $this->getResponse()->setStatusCode(405);
                }

                try {
                    $this->clearAnalysisReports();
                    return $this->jsonResponse(['success' => true]);
                } catch (Exception $e) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => $e->getMessage()
                    ], 500);
                }

            case 'export':
                try {
                    $data = $this->exportDebugData();
                    $response = $this->getResponse();
                    $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
                    $response->getHeaders()->addHeaderLine('Content-Disposition', 'attachment; filename="debug-export-' . date('Y-m-d-H-i-s') . '.json"');
                    $response->setContent(json_encode($data, JSON_PRETTY_PRINT));
                    return $response;
                } catch (Exception $e) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => $e->getMessage()
                    ], 500);
                }

            default:
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Unknown action'
                ], 404);
        }
    }

    /**
     * Action to serve DebugBar assets.
     */
    public function assetsAction(): Response
    {
        $response = $this->getResponse();
        $file = $this->params()->fromRoute('file');


        if (!$file) {
            $response->setStatusCode(404);
            return $response;
        }

        // Construct the full path to the asset file within the vendor directory
        $assetPath = dirname(__DIR__, 2) . '/vendor/maximebf/debugbar/src/DebugBar/Resources/' . $file;


        // Basic security check: prevent directory traversal
        if (strpos($file, '..') !== false || !file_exists($assetPath)) {
            $response->setStatusCode(404);
            return $response;
        }

        $extension = pathinfo($assetPath, PATHINFO_EXTENSION);
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
            $storagePath = $this->config->getStoragePath();
            $microscope = $this->getServiceLocator()->get(MicroscopeHandler::class);
            return $microscope->getRecentReports(5); 
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
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'current_memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'environment' => $this->config->get('laminas_microscope.environment', 'unknown'),
            'storage_path' => $this->config->get('laminas_microscope.storage.path', '/tmp/laminas-microscope'),
            'storage_writable' => is_writable(dirname($this->config->get('laminas_microscope.storage.path', '/tmp/laminas-microscope'))),
        ];
    }

    /**
     * Clear analysis reports
     */
    private function clearAnalysisReports(): void
    {
        try {
            $microscope = $this->getServiceLocator()->get(MicroscopeHandler::class);
            $microscope->clearReports();
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
            'export_date' => date('Y-m-d H:i:s'),
            'system_info' => $this->getSystemInfo(),
            'component_status' => [
                'whoops' => $this->componentManager->isEnabled('whoops'),
                'debug_bar' => $this->componentManager->isEnabled('debug_bar'),
                'microscope' => $this->componentManager->isEnabled('microscope'),
            ],
            'configuration' => $this->config->toArray(),
            'recent_reports' => $this->getRecentReports(),
        ];
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

