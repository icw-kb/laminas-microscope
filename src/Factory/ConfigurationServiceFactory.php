<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Exception;
// Corrected: Use Psr\\Container\\ContainerInterface
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Config\ConfigurationService;
use Psr\Container\ContainerInterface;

class ConfigurationServiceFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @param null|array $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ConfigurationService
    {
        try {
            // Get the main application configuration array
            // This should contain the merged config from all modules and autoload files
            $config = $container->get('Config');

            // Pass the *entire* configuration array to the ConfigurationService constructor
            // The ConfigurationService will handle extracting the 'laminas_microscope' key internally
            return new ConfigurationService($config);
        } catch (Exception $e) {
            // Fallback: Return ConfigurationService with empty config if 'Config' service is not available
            return new ConfigurationService([]);
        }
    }
}
