<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory\Controller;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Controller\ConfigurationController;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Service\ConfigurationManager;

class ConfigurationControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ConfigurationController
    {
        $componentManager = $container->get(ComponentManager::class);
        $config = $container->get(ConfigurationService::class);
        $configManager = $container->get(ConfigurationManager::class);
        
        return new ConfigurationController($componentManager, $config, $configManager);
    }
}