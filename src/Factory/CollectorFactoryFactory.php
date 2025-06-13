<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\DebugBar\CollectorFactory;
use Psr\Container\ContainerInterface;

class CollectorFactoryFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): CollectorFactory {
        $config           = $container->get('config');
        $collectorMapping = $config['laminas_microscope']['collector_mapping'] ?? [];

        return new CollectorFactory($container, $collectorMapping);
    }
}
