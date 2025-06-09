<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Collector\CollectorRegistry;

class CollectorRegistryFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): CollectorRegistry
    {
        return new CollectorRegistry();
    }
}