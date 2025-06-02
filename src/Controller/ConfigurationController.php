<?php

declare(strict_types=1);

namespace LaminasMicroscope\Controller;

use Laminas\Mvc\Controller\AbstractActionController; // Corrected namespace
use Laminas\View\Model\ViewModel; // Corrected namespace
use Laminas\View\Model\JsonModel; // Corrected namespace
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Service\ConfigurationManager;
use Exception; // Added use statement

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
        $viewModel = new ViewModel([ // Corrected namespace
            'config' => $this->config, // Changed from getAll() to toArray()
            'profiles' => $this->configManager->getAvailableProfiles(), // Assuming this method exists or needs implementation
            'currentEnvironment' => $this->configManager->getEnvironment(), // Changed from getCurrentEnvironment() to getEnvironment()
            'configPaths' => $this->configManager->getConfigurationPaths(), // Assuming this method exists or needs implementation
        ]);

        // *** FIX: Explicitly set the template name to match module.config.php ***
        $viewModel->setTemplate('laminas-microscope/config/index');

        return $viewModel;
    }
    public function profilesAction(): ViewModel
    {
        return new ViewModel([ // Corrected namespace
            'profiles' => $this->configManager->getAvailableProfiles(), // Assuming this method exists or needs implementation
            'currentEnvironment' => $this->configManager->getEnvironment(), // Changed from getCurrentEnvironment() to getEnvironment()
        ]);
    }

    public function loadProfileAction(): JsonModel
    {
        if (!$this->getRequest()->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'POST request required']); // Corrected namespace
        }

        $profileName = $this->params()->fromPost('profile');
        if (!$profileName) {
            return new JsonModel(['success' => false, 'message' => 'Profile name required']); // Corrected namespace
        }

        // Assuming ConfigurationManager has a loadProfile method
        $success = $this->configManager->loadProfile($profileName);

        return new JsonModel([ // Corrected namespace
            'success' => $success,
            'message' => $success ? "Profile '{$profileName}' loaded successfully" : "Failed to load profile '{$profileName}'",
            'profile' => $profileName,
        ]);
    }

    public function switchEnvironmentAction(): JsonModel
    {
        if (!$this->getRequest()->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'POST request required']); // Corrected namespace
        }

        $environment = $this->params()->fromPost('environment');
        if (!$environment) {
            return new JsonModel(['success' => false, 'message' => 'Environment required']); // Corrected namespace
        }

        // Assuming ConfigurationManager has a switchEnvironment method
        $success = $this->configManager->switchEnvironment($environment);

        return new JsonModel([ // Corrected namespace
            'success' => $success,
            'message' => $success ? "Switched to '{$environment}' environment" : "Invalid environment '{$environment}'",
            'environment' => $environment,
        ]);
    }

    public function exportAction()
    {
        $format = $this->params()->fromQuery('format', 'json'); // Changed default to json for web export
        // Assuming ConfigurationManager has an exportConfig method
        $content = $this->configManager->exportConfig(); // Changed from exportConfiguration() to exportConfig()

        $response = $this->getResponse();
        $response->getHeaders()
            ->addHeaderLine('Content-Type', $format === 'json' ? 'application/json' : 'text/yaml')
            ->addHeaderLine('Content-Disposition', "attachment; filename=\"laminas-microscope-config.{$format}\""); // Changed filename

        // Encode content if format is json
        if ($format === 'json') {
             $content = json_encode($content, JSON_PRETTY_PRINT);
        }
        // Note: YAML export would require a YAML library

        $response->setContent($content);
        return $response;
    }

    public function saveAction(): JsonModel
    {
        if (!$this->getRequest()->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'POST request required']); // Corrected namespace
        }

        $configData = json_decode($this->getRequest()->getContent(), true);

        if ($configData === null && json_last_error() !== JSON_ERROR_NONE) {
             return new JsonModel(['success' => false, 'message' => 'Invalid JSON data received']);
        }

        // Assuming ConfigurationManager has a saveUserConfiguration method
        // Or directly use ConfigurationService if saving to its internal state is intended
        // Based on the snapshot, ConfigurationService has setConfig and mergeConfig
        // Let's assume we want to merge the received config into the current one
        try {
             $this->config->mergeConfig($configData);
             // Note: Saving to a file would require additional logic,
             // likely in ConfigurationManager or a dedicated service.
             // For now, this only updates the runtime config.
             $success = true;
             $message = 'Configuration updated in runtime';
        } catch (Exception $e) { // Corrected namespace
             $success = false;
             $message = 'Failed to update configuration: ' . $e->getMessage();
        }


        return new JsonModel([ // Corrected namespace
            'success' => $success,
            'message' => $message,
        ]);
    }

    public function resetAction(): JsonModel
    {
        if (!$this->getRequest()->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'POST request required']); // Corrected namespace
        }

        $this->configManager->resetToDefaults();

        return new JsonModel([ // Corrected namespace
            'success' => true,
            'message' => 'Configuration reset to defaults',
        ]);
    }
}
