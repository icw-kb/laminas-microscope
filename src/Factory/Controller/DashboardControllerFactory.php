<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory\Controller;

use Exception;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Cache\CacheManager;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Controller\DashboardController;
use LaminasMicroscope\DebugBar\Collectors\EnhancedPDOCollector;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Microscope\Storage\ReportStorage;
use Psr\Container\ContainerInterface;

class DashboardControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DashboardController
    {
        $componentManager = $container->get(ComponentManager::class);
        $config           = $container->get(ConfigurationService::class);

        // Try to get Phase 3 services, but don't fail if they're not available
        $reportStorage        = null;
        $cacheManager         = null;
        $enhancedPDOCollector = null;

        try {
            if ($container->has(ReportStorage::class)) {
                $reportStorage = $container->get(ReportStorage::class);
            }
        } catch (Exception $e) {
            // Service not available, will use fallback
        }

        try {
            if ($container->has(CacheManager::class)) {
                $cacheManager = $container->get(CacheManager::class);
            }
        } catch (Exception $e) {
            // Service not available, will use fallback
        }

        try {
            if ($container->has(EnhancedPDOCollector::class)) {
                $enhancedPDOCollector = $container->get(EnhancedPDOCollector::class);
            }
        } catch (Exception $e) {
            // Service not available, will use fallback
        }

        return new DashboardController(
            $componentManager,
            $config,
            $reportStorage,
            $cacheManager,
            $enhancedPDOCollector
        );
    }
}
