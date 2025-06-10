<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Collector\CollectorRegistry;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Microscope\MicroscopeHandler;
use Psr\Container\ContainerInterface;

class MicroscopeHandlerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): MicroscopeHandler
    {
        $componentManager = $container->get(ComponentManager::class);
        $config           = $container->get(ConfigurationService::class);
        $registry         = $container->get(CollectorRegistry::class);

        return new MicroscopeHandler($componentManager, $config, $container, $registry);
    }
}
