<?php

declare(strict_types=1);

namespace LaminasMicroscope\Controller;

use Laminas\Mvc\Controller\AbstractActionController; // Import the base controller
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Service\AnalysisService;
use Laminas\View\Model\ViewModel; // Add ViewModel import if needed for actions
use Laminas\View\Model\JsonModel; // Add JsonModel import if needed for actions

/**
 * Main controller for the Laminas Microscope interface
 */
class MicroscopeController extends AbstractActionController // <--- Extend AbstractActionController
{
    // Note: Properties should ideally be private or protected
    // private object $request; // This property might conflict with AbstractActionController's request property

    // Constructor signature remains the same, AbstractActionController handles its own dependencies
    public function __construct(
        private ConfigurationService $configService,
        private AnalysisService $analysisService
    ) {
        // No need to call parent::__construct() unless you have specific logic there
    }

    /**
     * Set the request object (for testing)
     * This method might not be needed if extending AbstractActionController
     */
    // public function setRequest(object $request): void
    // {
    //     $this->request = $request; // This might override the parent's request
    // }

    /**
     * Main dashboard action
     */
    public function indexAction(): array // Or ViewModel
    {
        try {
            $analysis = $this->analysisService->getCurrentAnalysis();

            return [
                'analysis' => $analysis,
                'config' => $this->configService->toArray(),
                'environment' => $this->configService->getEnvironment(),
                'enabled' => $this->configService->isEnabled(),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'config' => $this->configService->toArray(),
                'environment' => $this->configService->getEnvironment(),
            ];
        }
    }

    /**
     * System dashboard action
     */
    public function dashboardAction(): array // Or ViewModel
    {
        $overview = $this->analysisService->getSystemOverview();

        return [
            'overview' => $overview,
            'components' => $this->configService->get('laminas_microscope.components', []),
            'storage_path' => $this->configService->getStoragePath(),
            'retention_days' => $this->configService->getRetentionDays(),
        ];
    }

    /**
     * Profiler action
     */
    public function profilerAction(): array // Or ViewModel
    {
        // You might need to get the session ID from the request object provided by AbstractActionController
        // $sessionId = $this->params()->fromRoute('id', 'current');
        $sessionId = 'current'; // Placeholder

        $profilerData = $this->analysisService->getProfilerData($sessionId);

        return [
            'profiler_data' => $profilerData,
            'session_id' => $sessionId,
        ];
    }

    /**
     * Analysis action - returns detailed analysis data
     */
    public function analysisAction(): array // Or ViewModel
    {
        return $this->analysisService->getDetailedAnalysis();
    }

    /**
     * Configuration action
     */
    public function configAction(): array // Or ViewModel
    {
        return [
            'config' => $this->configService->toArray(),
            'environment' => $this->configService->getEnvironment(),
            'storage_path' => $this->configService->getStoragePath(),
            'debug_mode' => $this->configService->isDebugMode(),
        ];
    }

    /**
     * Tools action
     */
    public function toolsAction(): array // Or ViewModel
    {
        return [
            'tools' => [
                'whoops' => $this->configService->getComponentConfig('whoops'),
                'debug_bar' => $this->configService->getComponentConfig('debug_bar'),
                'microscope' => $this->configService->getComponentConfig('microscope'),
            ],
            'available_analyzers' => [
                'performance',
                'database',
                'routes',
                'memory',
                'exceptions',
            ],
        ];
    }

    // Add any other necessary methods or adjust existing ones
    // to work with the AbstractActionController context (e.g., using $this->params(), $this->getRequest(), $this->getResponse())
}