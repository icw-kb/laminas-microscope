<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Service\ConfigurationManager;
use LaminasMicroscope\Config\ConfigurationService;

class ConfigurationManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ConfigurationManager
    {
        $config = $container->get(ConfigurationService::class);
        
        return new ConfigurationManager($config);
    }
}