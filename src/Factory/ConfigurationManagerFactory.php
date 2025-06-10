<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Service\ConfigurationManager;
use Psr\Container\ContainerInterface;

class ConfigurationManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ConfigurationManager
    {
        $config = $container->get(ConfigurationService::class);

        return new ConfigurationManager($config);
    }
}
