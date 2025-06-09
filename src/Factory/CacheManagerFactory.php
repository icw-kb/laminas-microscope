<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Cache\CacheManager;
use LaminasMicroscope\Config\ConfigurationService;

class CacheManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): CacheManager
    {
        $config = $container->get(ConfigurationService::class);
        
        return new CacheManager($config);
    }
}