<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Microscope\Storage\ReportStorage;
use Psr\Container\ContainerInterface;

class ReportStorageFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ReportStorage
    {
        $config = $container->get(ConfigurationService::class);

        return new ReportStorage($config);
    }
}
