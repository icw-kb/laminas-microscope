<?php

declare(strict_types=1);

namespace LaminasMicroscope\Service;

use LaminasMicroscope\Config\ConfigurationService;

/**
 * Configuration Manager Service
 *
 * This service manages configuration for the Laminas Microscope package
 */
class ConfigurationManager
{
    private ConfigurationService $configService;
    private array $defaultConfig;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
        $this->defaultConfig = $this->getDefaultConfiguration();
    }

    /**
     * Get configuration value with fallback to defaults
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->configService->get($key, $default);
    }

    /**
     * Get component-specific configuration
     */
    public function getComponentConfig(string $component): array
    {
        return $this->configService->getComponentConfig($component);
    }

    /**
     * Check if a component is enabled
     */
    public function isComponentEnabled(string $component): bool
    {
        $config = $this->getComponentConfig($component);
        return (bool) ($config['enabled'] ?? false);
    }

    /**
     * Get environment-specific settings
     */
    public function getEnvironmentConfig(): array
    {
        $environment = $this->configService->getEnvironment();
        return $this->get("environments.{$environment}", []);
    }

    /**
     * Validate configuration
     */
    public function validateConfig(): array
    {
        $errors = [];

        // Check storage path is writable
        $storagePath = $this->configService->getStoragePath();
        if (!is_dir($storagePath) && !mkdir($storagePath, 0755, true)) {
            $errors[] = "Storage path '{$storagePath}' is not writable";
        }

        // Check retention days is reasonable
        $retentionDays = $this->configService->getRetentionDays();
        if ($retentionDays < 1 || $retentionDays > 365) {
            $errors[] = "Retention days must be between 1 and 365, got {$retentionDays}";
        }

        // Check max file size is reasonable
        $maxSize = $this->configService->getMaxFileSize();
        if ($maxSize < 1024 || $maxSize > 1024 * 1024 * 1024) { // 1KB to 1GB
            $errors[] = "Max file size must be between 1KB and 1GB";
        }

        return $errors;
    }

    /**
     * Get default configuration structure
     */
    private function getDefaultConfiguration(): array
    {
        return [
            'laminas_microscope' => [
                'enabled' => true,
                'environment' => 'development',
                'debug_mode' => false,
                'storage' => [
                    'path' => 'data/laminas-microscope',
                    'retention_days' => 30,
                    'max_file_size' => 50 * 1024 * 1024, // 50MB
                    'allowed_extensions' => ['json', 'log', 'txt', 'xml'],
                ],
                'components' => [
                    'whoops' => [
                        'enabled' => true,
                        'show_in_production' => false,
                    ],
                    'debug_bar' => [
                        'enabled' => true,
                        'collectors' => ['time', 'memory', 'pdo'],
                    ],
                    'microscope' => [
                        'enabled' => true,
                        'auto_analyze' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Reset configuration to defaults
     */
    public function resetToDefaults(): void
    {
        $this->configService->setConfig($this->defaultConfig);
    }

    /**
     * Export current configuration
     */
    public function exportConfig(): array
    {
        return $this->configService->toArray();
    }

    /**
     * Get available configuration profiles
     *
     * @return string[]
     */
    public function getAvailableProfiles(): array
    {
        // Placeholder: In a real app, this would scan a directory like config/autoload/laminas-microscope-profiles/
        // Returning a hardcoded list for now to satisfy the return type
        return ['minimal', 'performance', 'debugging'];
    }

    /**
     * Get loaded configuration file paths
     *
     * @return string[]
     */
    public function getConfigurationPaths(): array
    {
        // Placeholder: In a real app, this would show loaded config files
        // Returning a hardcoded list for now to satisfy the return type
        return ['config/autoload/laminas-microscope.local.php'];
    }

    /**
     * Get the current environment from the config service
     */
    public function getEnvironment(): string
    {
        return $this->configService->getEnvironment();
    }

    /**
     * Load a configuration profile
     *
     * @param string $profileName
     * @return bool
     */
    public function loadProfile(string $profileName): bool
    {
        // Placeholder: In a real app, this would load a profile config file and merge it
        error_log("Simulating loading profile: {$profileName}");
        // Simulate success for now
        return true;
    }

    /**
     * Switch the current environment
     *
     * @param string $environment
     * @return bool
     */
    public function switchEnvironment(string $environment): bool
    {
         // Placeholder: In a real app, this would update the environment setting
         // and potentially trigger a config reload or merge environment-specific config
         error_log("Simulating switching environment to: {$environment}");
         $this->configService->set('laminas_microscope.environment', $environment);
         // Simulate success for now
         return true;
    }
}
