<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Collector\CollectorRegistry;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\DebugBar\CollectorFactory;
use LaminasMicroscope\DebugBar\DebugBarHandler;
use Psr\Container\ContainerInterface;

class DebugBarHandlerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DebugBarHandler
    {
        $config           = $container->get(ConfigurationService::class);
        $registry         = $container->get(CollectorRegistry::class);
        $collectorFactory = $container->get(CollectorFactory::class);

        return new DebugBarHandler($config, $container, $registry, $collectorFactory);
    }
}
