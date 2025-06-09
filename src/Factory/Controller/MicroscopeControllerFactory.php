<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory\Controller;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Controller\MicroscopeController;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Service\AnalysisService;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Microscope\MicroscopeHandler;

class MicroscopeControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): MicroscopeController
    {
        $config = $container->get(ConfigurationService::class);
        $analysisService = $container->get(AnalysisService::class);
        $componentManager = $container->get(ComponentManager::class);
        $microscopeHandler = $container->get(MicroscopeHandler::class);
        
        return new MicroscopeController($config, $analysisService, $componentManager, $microscopeHandler);
    }
}