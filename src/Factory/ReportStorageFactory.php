<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Microscope\Storage\ReportStorage;
use LaminasMicroscope\Config\ConfigurationService;

class ReportStorageFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ReportStorage
    {
        $config = $container->get(ConfigurationService::class);
        
        return new ReportStorage($config);
    }
}