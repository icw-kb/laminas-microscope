<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Psr\Container\ContainerInterface; // Corrected: Use Psr\\Container\\ContainerInterface
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Config\ConfigurationService;
use Exception;

class ConfigurationServiceFactory implements FactoryInterface
{
    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param null|array $options
     * @return ConfigurationService
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ConfigurationService // Removed 'string' type hint from $requestedName
    {
        // --- NEW DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: ConfigurationServiceFactory __invoke called.\n");
        // --- END DEBUG LOGGING ---

        try {
            // Get the main application configuration array
            // This should contain the merged config from all modules and autoload files
            $config = $container->get('Config');

            // --- NEW DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: ConfigurationServiceFactory - Retrieved 'Config' from container. Type: " . gettype($config) . ".\n");
            error_log("LaminasMicroscope: DEBUG: ConfigurationServiceFactory - Content of 'Config'['laminas_microscope']: " . json_encode($config['laminas_microscope'] ?? 'laminas_microscope key not found') . ".\n");
            // --- END NEW DEBUG LOGGING ---

            // Pass the *entire* configuration array to the ConfigurationService constructor
            // The ConfigurationService will handle extracting the 'laminas_microscope' key internally
            return new ConfigurationService($config);

        } catch (Exception $e) {
            // --- NEW DEBUG LOGGING ---
            error_log("LaminasMicroscope: ERROR: ConfigurationServiceFactory failed to retrieve 'Config' or instantiate ConfigurationService: " . $e->getMessage() . ".\n");
            // --- END NEW DEBUG LOGGING ---

            // Fallback: Return ConfigurationService with empty config if 'Config' service is not available
            return new ConfigurationService([]);
        }
    }
}
