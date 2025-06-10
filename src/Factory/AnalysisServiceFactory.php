<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Microscope\MicroscopeHandler;
use LaminasMicroscope\Service\AnalysisService;
use Psr\Container\ContainerInterface;

class AnalysisServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AnalysisService
    {
        $config            = $container->get(ConfigurationService::class);
        $microscopeHandler = $container->get(MicroscopeHandler::class);

        return new AnalysisService($config, $microscopeHandler);
    }
}
