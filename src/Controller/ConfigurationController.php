<?php

declare(strict_types=1);

namespace LaminasMicroscope\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel; 
use Laminas\View\Model\JsonModel; 
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Service\ConfigurationManager;
use Exception;

class ConfigurationController extends AbstractActionController
{
    private ComponentManager $componentManager;
    private ConfigurationService $config;
    private ConfigurationManager $configManager;

    public function __construct(
        ComponentManager $componentManager,
        ConfigurationService $config,
        ConfigurationManager $configManager
    ) {
        $this->componentManager = $componentManager;
        $this->config = $config;
        $this->configManager = $configManager;
    }

 public function indexAction(): ViewModel
    {
        $viewModel = new ViewModel([ 
            'config' => $this->config, 
            'profiles' => $this->configManager->getAvailableProfiles(),
            'currentEnvironment' => $this->configManager->getEnvironment(), 
            'configPaths' => $this->configManager->getConfigurationPaths(), 
        ]);

        $viewModel->setTemplate('laminas-microscope/config/index');

        return $viewModel;
    }
    public function profilesAction(): ViewModel
    {
        return new ViewModel([ 
            'profiles' => $this->configManager->getAvailableProfiles(), 
            'currentEnvironment' => $this->configManager->getEnvironment(), 
        ]);
    }

    public function loadProfileAction(): JsonModel
    {
        if (!$this->getRequest()->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'POST request required']); 
        }

        $profileName = $this->params()->fromPost('profile');
        if (!$profileName) {
            return new JsonModel(['success' => false, 'message' => 'Profile name required']); 
        }

        $success = $this->configManager->loadProfile($profileName);

        return new JsonModel([ 
            'success' => $success,
            'message' => $success ? "Profile '{$profileName}' loaded successfully" : "Failed to load profile '{$profileName}'",
            'profile' => $profileName,
        ]);
    }

    public function switchEnvironmentAction(): JsonModel
    {
        if (!$this->getRequest()->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'POST request required']); 
        }

        $environment = $this->params()->fromPost('environment');
        if (!$environment) {
            return new JsonModel(['success' => false, 'message' => 'Environment required']); 
        }

        $success = $this->configManager->switchEnvironment($environment);

        return new JsonModel([ 
            'success' => $success,
            'message' => $success ? "Switched to '{$environment}' environment" : "Invalid environment '{$environment}'",
            'environment' => $environment,
        ]);
    }

    public function exportAction()
    {
        $format = $this->params()->fromQuery('format', 'json'); 
        $content = $this->configManager->exportConfig(); 

        $response = $this->getResponse();
        $response->getHeaders()
            ->addHeaderLine('Content-Type', $format === 'json' ? 'application/json' : 'text/yaml')
            ->addHeaderLine('Content-Disposition', "attachment; filename=\"laminas-microscope-config.{$format}\""); // Changed filename

        // Encode content if format is json
        if ($format === 'json') {
             $content = json_encode($content, JSON_PRETTY_PRINT);
        }

        $response->setContent($content);
        return $response;
    }

    public function saveAction(): JsonModel
    {
        if (!$this->getRequest()->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'POST request required']); 
        }

        $configData = json_decode($this->getRequest()->getContent(), true);

        if ($configData === null && json_last_error() !== JSON_ERROR_NONE) {
             return new JsonModel(['success' => false, 'message' => 'Invalid JSON data received']);
        }

        try {
             $this->config->mergeConfig($configData);
             // Note: Saving to a file would require additional logic,
             // likely in ConfigurationManager or a dedicated service.
             // For now, this only updates the runtime config.
             $success = true;
             $message = 'Configuration updated in runtime';
        } catch (Exception $e) { 
             $success = false;
             $message = 'Failed to update configuration: ' . $e->getMessage();
        }


        return new JsonModel([ 
            'success' => $success,
            'message' => $message,
        ]);
    }

    public function resetAction(): JsonModel
    {
        if (!$this->getRequest()->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'POST request required']); 
        }

        $this->configManager->resetToDefaults();

        return new JsonModel([ 
            'success' => true,
            'message' => 'Configuration reset to defaults',
        ]);
    }
}
