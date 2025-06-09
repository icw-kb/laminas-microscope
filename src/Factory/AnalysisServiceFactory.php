<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Service\AnalysisService;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Microscope\MicroscopeHandler;

class AnalysisServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AnalysisService
    {
        $config = $container->get(ConfigurationService::class);
        $microscopeHandler = $container->get(MicroscopeHandler::class);
        
        return new AnalysisService($config, $microscopeHandler);
    }
}