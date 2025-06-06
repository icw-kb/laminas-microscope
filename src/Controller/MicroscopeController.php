<?php

declare(strict_types=1);

namespace LaminasMicroscope\Controller;

use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Service\AnalysisService;
use Laminas\Mvc\Controller\AbstractActionController; 
use Laminas\View\Model\ViewModel; 
use Laminas\View\Model\JsonModel; 
use LaminasMicroscope\Manager\ComponentManager; 
use LaminasMicroscope\Microscope\MicroscopeHandler; 
use Exception; 

/**
 * Main controller for the Laminas Microscope interface
 */
class MicroscopeController extends AbstractActionController 
{
    private ConfigurationService $configService;
    private AnalysisService $analysisService;
    private ComponentManager $componentManager; 
    private MicroscopeHandler $microscopeHandler; 

    public function __construct(
        ConfigurationService $configService,
        AnalysisService $analysisService,
        ComponentManager $componentManager,
        MicroscopeHandler $microscopeHandler 
    ) {
        $this->configService = $configService;
        $this->analysisService = $analysisService;
        $this->componentManager = $componentManager;
        $this->microscopeHandler = $microscopeHandler; 
    }

    /**
     * Main dashboard action
     */
    public function indexAction(): ViewModel 
    {
        try {
            $analysisData = $this->analysisService->getCurrentAnalysis();

            $viewModel = new ViewModel([
                'analysisData' => $analysisData, 
                'config' => $this->configService->toArray(),
                'environment' => $this->configService->getEnvironment(),
                'enabled' => $this->componentManager->isEnabled('microscope'), 
                'queries' => $analysisData['database'] ?? [],
                'routes' => $analysisData['routes'] ?? [],
                'performance' => $analysisData['performance'] ?? [],
                'issues' => $analysisData['issues'] ?? [],
                'summary' => $analysisData['summary'] ?? [],
                'recentReports' => $this->microscopeHandler->getRecentReports() ?? [],
            ]);

            $viewModel->setTemplate('laminas-microscope/microscope/index');
            return $viewModel;

        } catch (Exception $e) { 
            error_log("Laminas Microscope Error in indexAction: " . $e->getMessage());

            $viewModel = new ViewModel([
                'error' => $e->getMessage(),
                'config' => $this->configService->toArray(),
                'environment' => $this->configService->getEnvironment(),
                'enabled' => $this->componentManager->isEnabled('microscope'),
                'queries' => [],
                'routes' => [],
                'performance' => [],
                'issues' => [],
                'summary' => [],
                'recentReports' => [],
            ]);
            $viewModel->setTemplate('laminas-microscope/microscope/index'); 
            return $viewModel;
        }
    }

    /**
     * System dashboard action (Placeholder - might be redundant with main dashboard)
     */
    public function dashboardAction(): ViewModel // Return ViewModel
    {
        // This action might be redundant with the main dashboard controller (DashboardController)
        // For now, return a basic view model
         $viewModel = new ViewModel([
            'message' => 'This action is a placeholder or might be redundant.',
         ]);
         $viewModel->setTemplate('laminas-microscope/microscope/dashboard'); 
         return $viewModel;
    }

    /**
     * Profiler action (Placeholder - needs implementation to load specific report)
     */
    public function profilerAction(): ViewModel 
    {
        $sessionId = $this->params()->fromRoute('id', 'current'); 

        try {
            $profilerData = $this->analysisService->getProfilerData($sessionId);

            $viewModel = new ViewModel([
                'profiler_data' => $profilerData,
                'session_id' => $sessionId,
            ]);
            $viewModel->setTemplate('laminas-microscope/microscope/profiler'); 
            return $viewModel;

        } catch (Exception $e) { 
             // Log the error
            error_log("Laminas Microscope Error in profilerAction: " . $e->getMessage());

            $viewModel = new ViewModel([
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
                'profiler_data' => [],
            ]);
            $viewModel->setTemplate('laminas-microscope/microscope/profiler'); 
            return $viewModel;
        }
    }

    /**
     * Analysis action - returns detailed analysis data (Placeholder - needs implementation)
     */
    public function analysisAction(): JsonModel 
    {
        try {
            $detailedAnalysis = $this->analysisService->getDetailedAnalysis();
            return new JsonModel($detailedAnalysis); 
        } catch (Exception $e) { 
            return new JsonModel(['error' => $e->getMessage()], 500); 
        }
    }

    /**
     * Configuration action (Placeholder - might be redundant with ConfigurationController)
     */
    public function configAction(): ViewModel // Return ViewModel
    {
        $viewModel = new ViewModel([
            'message' => 'This action is a placeholder or might be redundant.',
        ]);
        $viewModel->setTemplate('laminas-microscope/microscope/config'); 
        return $viewModel;
    }

    /**
     * Tools action (Placeholder - needs implementation)
     */
    public function toolsAction(): ViewModel // Return ViewModel
    {
        $viewModel = new ViewModel([
            'message' => 'This action is a placeholder or needs implementation.',
        ]);
        $viewModel->setTemplate('laminas-microscope/microscope/tools'); 
        return $viewModel;
    }

    /**
     * API endpoints for Microscope
     */
    public function apiAction(): JsonModel 
    {
        $request = $this->getRequest();
        $action = $this->params()->fromRoute('action', 'status');

        if (!$request->isPost() && !$request->isGet()) {
            return new JsonModel(['success' => false, 'message' => 'Method not allowed'], 405); 
        }

        switch ($action) {
            case 'run-analysis':
                if (!$request->isPost()) {
                    return new JsonModel(['success' => false, 'message' => 'POST request required'], 405); 
                }
                try {
                    $report = $this->microscopeHandler->runAnalysis(); 

                    return new JsonModel(['success' => true, 'report' => $report]); 
                } catch (Exception $e) { 
                    return new JsonModel(['success' => false, 'message' => $e->getMessage()], 500); 
                }

            case 'clear-reports':
                  return new JsonModel(['success' => false, 'message' => 'Action handled by Dashboard API'], 400); 

            case 'export':
                  return new JsonModel(['success' => false, 'message' => 'Action handled by Dashboard API'], 400);

            case 'delete-report':
                if (!$request->isPost()) {
                    return new JsonModel(['success' => false, 'message' => 'POST request required'], 405); 
                }
                $reportId = $this->params()->fromPost('reportId');
                 if (!$reportId) {
                    return new JsonModel(['success' => false, 'message' => 'Report ID required'], 400); 
                }
                try {
                     $success = $this->microscopeHandler->deleteReport($reportId);

                    if ($success) {
                         return new JsonModel(['success' => true, 'message' => "Report '{$reportId}' deleted"]); 
                    } else {
                         return new JsonModel(['success' => false, 'message' => "Failed to delete report '{$reportId}'"], 404); 
                    }
                } catch (Exception $e) {
                    return new JsonModel(['success' => false, 'message' => $e->getMessage()], 500);
                }

            default:
                return new JsonModel(['success' => false, 'message' => 'Unknown action'], 404); 
        }
    }
}
