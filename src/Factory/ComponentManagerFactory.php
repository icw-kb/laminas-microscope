<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Collector\CollectorRegistry;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Manager\ComponentManager;
use Psr\Container\ContainerInterface;

class ComponentManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ComponentManager
    {
        $config   = $container->get(ConfigurationService::class);
        $registry = $container->get(CollectorRegistry::class);

        return new ComponentManager($config, $registry, $container);
    }
}
