<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory\Controller;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Controller\DashboardController;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;

class DashboardControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DashboardController
    {
        $componentManager = $container->get(ComponentManager::class);
        $config = $container->get(ConfigurationService::class);
        
        return new DashboardController($componentManager, $config);
    }
}