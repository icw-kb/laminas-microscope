<?php

declare(strict_types=1);

namespace LaminasMicroscope\Controller;

use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Service\AnalysisService;
use Laminas\Mvc\Controller\AbstractActionController; // Added use statement
use Laminas\View\Model\ViewModel; // Added use statement
use Laminas\View\Model\JsonModel; // Added use statement
use LaminasMicroscope\Manager\ComponentManager; // Added use statement
use LaminasMicroscope\Microscope\MicroscopeHandler; // Added use statement
use Exception; // Added use statement

/**
 * Main controller for the Laminas Microscope interface
 */
class MicroscopeController extends AbstractActionController // Extend AbstractActionController
{
    private ConfigurationService $configService;
    private AnalysisService $analysisService;
    private ComponentManager $componentManager; // Inject ComponentManager
    private MicroscopeHandler $microscopeHandler; // Inject MicroscopeHandler

    public function __construct(
        ConfigurationService $configService,
        AnalysisService $analysisService,
        ComponentManager $componentManager, // Inject ComponentManager
        MicroscopeHandler $microscopeHandler // Inject MicroscopeHandler
    ) {
        $this->configService = $configService;
        $this->analysisService = $analysisService;
        $this->componentManager = $componentManager;
        $this->microscopeHandler = $microscopeHandler; // Assign injected handler
    }

    /**
     * Main dashboard action
     */
    public function indexAction(): ViewModel // Return ViewModel
    {
        try {
            // Get analysis data from AnalysisService
            // AnalysisService now gets data from MicroscopeHandler internally
            $analysisData = $this->analysisService->getCurrentAnalysis();

            $viewModel = new ViewModel([
                'analysisData' => $analysisData, // Pass the whole analysis data
                'config' => $this->configService->toArray(),
                'environment' => $this->configService->getEnvironment(),
                'enabled' => $this->componentManager->isEnabled('microscope'), // Check microscope enabled status
                // Pass specific data points needed by the view
                'queries' => $analysisData['database'] ?? [],
                'routes' => $analysisData['routes'] ?? [],
                'performance' => $analysisData['performance'] ?? [],
                'issues' => $analysisData['issues'] ?? [],
                'summary' => $analysisData['summary'] ?? [],
                // Pass recent reports - now get from MicroscopeHandler
                'recentReports' => $this->microscopeHandler->getRecentReports() ?? [],
            ]);

            $viewModel->setTemplate('laminas-microscope/microscope/index');
            return $viewModel;

        } catch (Exception $e) { // Corrected namespace
            // Log the error
            error_log("Laminas Microscope Error in indexAction: " . $e->getMessage());

            // Return an error view model
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
            $viewModel->setTemplate('laminas-microscope/microscope/index'); // Render the same template with error info
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
         $viewModel->setTemplate('laminas-microscope/microscope/dashboard'); // Create this template if needed
         return $viewModel;
    }

    /**
     * Profiler action (Placeholder - needs implementation to load specific report)
     */
    public function profilerAction(): ViewModel // Return ViewModel
    {
        $sessionId = $this->params()->fromRoute('id', 'current'); // Get session ID from route or use 'current'

        try {
            // AnalysisService::getProfilerData should handle loading from storage or getting current
            $profilerData = $this->analysisService->getProfilerData($sessionId);

            $viewModel = new ViewModel([
                'profiler_data' => $profilerData,
                'session_id' => $sessionId,
            ]);
            $viewModel->setTemplate('laminas-microscope/microscope/profiler'); // Create this template if needed
            return $viewModel;

        } catch (Exception $e) { // Corrected namespace
             // Log the error
            error_log("Laminas Microscope Error in profilerAction: " . $e->getMessage());

            $viewModel = new ViewModel([
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
                'profiler_data' => [],
            ]);
            $viewModel->setTemplate('laminas-microscope/microscope/profiler'); // Render the same template with error info
            return $viewModel;
        }
    }

    /**
     * Analysis action - returns detailed analysis data (Placeholder - needs implementation)
     */
    public function analysisAction(): JsonModel // Return JsonModel
    {
        // This action might be used for API calls to get detailed analysis
        try {
            $detailedAnalysis = $this->analysisService->getDetailedAnalysis();
            return new JsonModel($detailedAnalysis); // Corrected namespace
        } catch (Exception $e) { // Corrected namespace
            return new JsonModel(['error' => $e->getMessage()], 500); // Corrected namespace
        }
    }

    /**
     * Configuration action (Placeholder - might be redundant with ConfigurationController)
     */
    public function configAction(): ViewModel // Return ViewModel
    {
        // This action might be redundant with the main configuration controller (ConfigurationController)
        // For now, return a basic view model
        $viewModel = new ViewModel([
            'message' => 'This action is a placeholder or might be redundant.',
        ]);
        $viewModel->setTemplate('laminas-microscope/microscope/config'); // Create this template if needed
        return $viewModel;
    }

    /**
     * Tools action (Placeholder - needs implementation)
     */
    public function toolsAction(): ViewModel // Return ViewModel
    {
        // This action might list available tools or provide access to them
        // For now, return a basic view model
        $viewModel = new ViewModel([
            'message' => 'This action is a placeholder or needs implementation.',
        ]);
        $viewModel->setTemplate('laminas-microscope/microscope/tools'); // Create this template if needed
        return $viewModel;
    }

    /**
     * API endpoints for Microscope
     */
    public function apiAction(): JsonModel // Return JsonModel
    {
        $request = $this->getRequest();
        $action = $this->params()->fromRoute('action', 'status');

        if (!$request->isPost() && !$request->isGet()) {
            return new JsonModel(['success' => false, 'message' => 'Method not allowed'], 405); // Corrected namespace
        }

        switch ($action) {
            case 'run-analysis':
                if (!$request->isPost()) {
                    return new JsonModel(['success' => false, 'message' => 'POST request required'], 405); // Corrected namespace
                }
                try {
                    // Trigger analysis via MicroscopeHandler
                    $report = $this->microscopeHandler->runAnalysis(); // Use the existing runAnalysis method

                    // Optionally save the report if not auto-saved
                    // $this->microscopeHandler->saveReport($report); // Assuming saveReport method exists

                    return new JsonModel(['success' => true, 'report' => $report]); // Corrected namespace
                } catch (Exception $e) { // Corrected namespace
                    return new JsonModel(['success' => false, 'message' => $e->getMessage()], 500); // Corrected namespace
                }

            case 'clear-reports':
                 // This action is already in DashboardController::apiAction
                 // Avoid duplication or decide which controller handles it
                 return new JsonModel(['success' => false, 'message' => 'Action handled by Dashboard API'], 400); // Corrected namespace

            case 'export':
                // This action is already in DashboardController::apiAction
                // Avoid duplication or decide which controller handles it
                 return new JsonModel(['success' => false, 'message' => 'Action handled by Dashboard API'], 400); // Corrected namespace

            case 'delete-report':
                if (!$request->isPost()) {
                    return new JsonModel(['success' => false, 'message' => 'POST request required'], 405); // Corrected namespace
                }
                $reportId = $this->params()->fromPost('reportId');
                 if (!$reportId) {
                    return new JsonModel(['success' => false, 'message' => 'Report ID required'], 400); // Corrected namespace
                }
                try {
                    // Assuming MicroscopeHandler has a deleteReport method
                    $success = $this->microscopeHandler->deleteReport($reportId); // Assuming deleteReport method exists

                    if ($success) {
                         return new JsonModel(['success' => true, 'message' => "Report '{$reportId}' deleted"]); // Corrected namespace
                    } else {
                         return new JsonModel(['success' => false, 'message' => "Failed to delete report '{$reportId}'"], 404); // Corrected namespace
                    }
                } catch (Exception $e) { // Corrected namespace
                    return new JsonModel(['success' => false, 'message' => $e->getMessage()], 500); // Corrected namespace
                }

            default:
                return new JsonModel(['success' => false, 'message' => 'Unknown action'], 404); // Corrected namespace
        }
    }
}
