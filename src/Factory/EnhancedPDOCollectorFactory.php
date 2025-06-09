<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\DebugBar\Collectors\EnhancedPDOCollector;
use LaminasMicroscope\Cache\CacheManager;
use LaminasMicroscope\Config\ConfigurationService;

class EnhancedPDOCollectorFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): EnhancedPDOCollector
    {
        $serviceManager = $container;
        $cacheManager = $container->has(CacheManager::class) ? $container->get(CacheManager::class) : null;
        $configService = $container->get(ConfigurationService::class);
        
        $config = $configService->getComponentConfig('enhanced_pdo');
        
        return new EnhancedPDOCollector($serviceManager, $cacheManager, $config);
    }
}