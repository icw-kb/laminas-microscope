<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\DebugBar\DebugBarHandler;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Collector\CollectorRegistry;

class DebugBarHandlerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DebugBarHandler
    {
        $config = $container->get(ConfigurationService::class);
        $registry = $container->get(CollectorRegistry::class);
        
        return new DebugBarHandler($config, $container, $registry);
    }
}