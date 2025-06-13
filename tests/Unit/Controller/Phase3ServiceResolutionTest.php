<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Unit\Controller;

use LaminasMicroscopeTest\Unit\BaseTestCase;
use LaminasMicroscope\Controller\DashboardController;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Microscope\Storage\ReportStorage;
use LaminasMicroscope\Cache\CacheManager;
use LaminasMicroscope\Collector\EnhancedPDOCollector;
use Laminas\ServiceManager\ServiceManager;

/**
 * Test specifically for the service resolution issue we encountered
 * where Phase 3 controllers failed with "Undefined variable" errors
 */
class Phase3ServiceResolutionTest extends BaseTestCase
{
    /**
     * Test that ReportStorage can be instantiated without service manager
     */
    public function testReportStorageInstantiationFallback(): void
    {
        $config = new ConfigurationService([
            'laminas_microscope' => [
                'storage' => ['path' => sys_get_temp_dir() . '/lm-test'],
                'enabled' => true
            ]
        ]);
        
        // This should not throw an exception
        $reportStorage = new ReportStorage($config);
        $this->assertInstanceOf(ReportStorage::class, $reportStorage);
        
        // Basic methods should work without database
        $queryAnalysis = $reportStorage->getQueryAnalysis();
        $this->assertIsArray($queryAnalysis);
        $this->assertArrayHasKey('total_queries', $queryAnalysis);
        $this->assertEquals(0, $queryAnalysis['total_queries']);
        
        $routeAnalysis = $reportStorage->getRouteAnalysis();
        $this->assertIsArray($routeAnalysis);
        $this->assertArrayHasKey('total_requests', $routeAnalysis);
        
        $performanceData = $reportStorage->getPerformanceData();
        $this->assertIsArray($performanceData);
        $this->assertArrayHasKey('total_requests', $performanceData);
        
        $summary = $reportStorage->getSummary();
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('performance_score', $summary);
    }

    /**
     * Test that CacheManager can be instantiated without service manager
     */
    public function testCacheManagerInstantiationFallback(): void
    {
        $config = new ConfigurationService([
            'laminas_microscope' => [
                'storage' => ['path' => sys_get_temp_dir() . '/lm-test'],
                'enabled' => true
            ]
        ]);
        
        // This should not throw an exception
        $cacheManager = new CacheManager($config);
        $this->assertInstanceOf(CacheManager::class, $cacheManager);
        
        // Basic methods should work
        $stats = $cacheManager->getStats();
        $this->assertIsArray($stats);
        
        // Test basic cache operations
        $this->assertTrue($cacheManager->set('test_key', 'test_value'));
        $this->assertEquals('test_value', $cacheManager->get('test_key'));
        $this->assertTrue($cacheManager->has('test_key'));
        $this->assertTrue($cacheManager->delete('test_key'));
        $this->assertNull($cacheManager->get('test_key'));
    }

    /**
     * Test that service manager registration is correct
     */
    public function testServiceManagerRegistration(): void
    {
        // Load the module config
        $config = include __DIR__ . '/../../../config/module.config.php';
        
        $this->assertArrayHasKey('service_manager', $config);
        $this->assertArrayHasKey('factories', $config['service_manager']);
        
        $factories = $config['service_manager']['factories'];
        
        // Verify Phase 3 services are registered
        $this->assertArrayHasKey(ReportStorage::class, $factories);
        $this->assertArrayHasKey(CacheManager::class, $factories);
        $this->assertArrayHasKey(EnhancedPDOCollector::class, $factories);
        
        // Verify factory classes exist
        $this->assertEquals('LaminasMicroscope\Factory\ReportStorageFactory', $factories[ReportStorage::class]);
        $this->assertEquals('LaminasMicroscope\Factory\CacheManagerFactory', $factories[CacheManager::class]);
        $this->assertEquals('LaminasMicroscope\Factory\EnhancedPDOCollectorFactory', $factories[EnhancedPDOCollector::class]);
    }

    /**
     * Test that factory classes exist and are instantiable
     */
    public function testPhase3FactoriesExist(): void
    {
        $factories = [
            'LaminasMicroscope\Factory\ReportStorageFactory',
            'LaminasMicroscope\Factory\CacheManagerFactory', 
            'LaminasMicroscope\Factory\EnhancedPDOCollectorFactory'
        ];
        
        foreach ($factories as $factoryClass) {
            $this->assertTrue(class_exists($factoryClass), "Factory class {$factoryClass} should exist");
            
            $reflection = new \ReflectionClass($factoryClass);
            $this->assertTrue($reflection->implementsInterface('Laminas\ServiceManager\Factory\FactoryInterface'));
        }
    }

    /**
     * Test that service instantiation works with minimal dependencies
     */
    public function testServiceInstantiationWithMinimalDependencies(): void
    {
        $config = new ConfigurationService([
            'laminas_microscope' => [
                'storage' => ['path' => sys_get_temp_dir() . '/lm-test'],
                'enabled' => true
            ]
        ]);
        
        $serviceManager = new ServiceManager();
        $serviceManager->setService(ConfigurationService::class, $config);
        
        // Test ReportStorageFactory
        $reportStorageFactory = new \LaminasMicroscope\Factory\ReportStorageFactory();
        $reportStorage = $reportStorageFactory($serviceManager, ReportStorage::class);
        $this->assertInstanceOf(ReportStorage::class, $reportStorage);
        
        // Test CacheManagerFactory  
        $cacheManagerFactory = new \LaminasMicroscope\Factory\CacheManagerFactory();
        $cacheManager = $cacheManagerFactory($serviceManager, CacheManager::class);
        $this->assertInstanceOf(CacheManager::class, $cacheManager);
        
        // Test EnhancedPDOCollectorFactory
        $enhancedPDOFactory = new \LaminasMicroscope\Factory\EnhancedPDOCollectorFactory();
        $collector = $enhancedPDOFactory($serviceManager, EnhancedPDOCollector::class);
        $this->assertInstanceOf(EnhancedPDOCollector::class, $collector);
    }

    /**
     * Test that the exact scenario that caused "Undefined variable $analytics" is fixed
     */
    public function testAnalyticsDataStructure(): void
    {
        $config = new ConfigurationService([
            'laminas_microscope' => [
                'storage' => ['path' => sys_get_temp_dir() . '/lm-test'],
                'enabled' => true
            ]
        ]);
        
        $reportStorage = new ReportStorage($config);
        
        // This is the exact code that was failing in the controller
        $analytics = [
            'query_analysis' => $reportStorage->getQueryAnalysis(),
            'route_analysis' => $reportStorage->getRouteAnalysis(), 
            'performance_data' => $reportStorage->getPerformanceData(),
            'summary' => $reportStorage->getSummary(),
        ];
        
        // Verify the structure that was causing undefined variable errors
        $this->assertArrayHasKey('query_analysis', $analytics);
        $this->assertArrayHasKey('route_analysis', $analytics);
        $this->assertArrayHasKey('performance_data', $analytics);
        $this->assertArrayHasKey('summary', $analytics);
        
        // Verify nested structure that templates expect
        $this->assertArrayHasKey('total_queries', $analytics['query_analysis']);
        $this->assertArrayHasKey('slow_queries', $analytics['query_analysis']);
        $this->assertArrayHasKey('n_plus_one_patterns', $analytics['query_analysis']);
        
        $this->assertArrayHasKey('total_requests', $analytics['route_analysis']);
        $this->assertArrayHasKey('popular_routes', $analytics['route_analysis']);
        
        $this->assertArrayHasKey('total_requests', $analytics['performance_data']);
        $this->assertArrayHasKey('average_response_time', $analytics['performance_data']);
        
        $this->assertArrayHasKey('performance_score', $analytics['summary']);
        $this->assertArrayHasKey('recommendations', $analytics['summary']);
    }

    /**
     * Test cache stats structure for template compatibility
     */
    public function testCacheStatsStructure(): void
    {
        $config = new ConfigurationService([
            'laminas_microscope' => [
                'storage' => ['path' => sys_get_temp_dir() . '/lm-test'],
                'enabled' => true
            ]
        ]);
        
        $cacheManager = new CacheManager($config);
        $cacheStats = $cacheManager->getStats();
        
        // Verify structure that cache template expects
        $this->assertIsArray($cacheStats);
        $this->assertArrayHasKey('default', $cacheStats);
        
        $defaultStats = $cacheStats['default'];
        $this->assertArrayHasKey('type', $defaultStats);
        $this->assertArrayHasKey('stats', $defaultStats);
        
        $stats = $defaultStats['stats'];
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('writes', $stats);
        $this->assertArrayHasKey('deletes', $stats);
    }

    /**
     * Test performance data structure for template compatibility
     */
    public function testPerformanceDataStructure(): void
    {
        $serviceManager = new ServiceManager();
        $config = new ConfigurationService([
            'laminas_microscope' => [
                'storage' => ['path' => sys_get_temp_dir() . '/lm-test'],
                'enabled' => true
            ]
        ]);
        
        $collector = new EnhancedPDOCollector($serviceManager);
        $performanceData = $collector->collect();
        
        // Verify structure that performance template expects
        $this->assertArrayHasKey('performance_score', $performanceData);
        $this->assertArrayHasKey('nb_statements', $performanceData);
        $this->assertArrayHasKey('nb_slow_statements', $performanceData);
        $this->assertArrayHasKey('nb_duplicate_statements', $performanceData);
        $this->assertArrayHasKey('accumulated_duration_str', $performanceData);
        $this->assertArrayHasKey('analysis', $performanceData);
        $this->assertArrayHasKey('recommendations', $performanceData);
        $this->assertArrayHasKey('statements', $performanceData);
        
        // Verify nested analysis structure
        $analysis = $performanceData['analysis'];
        $this->assertArrayHasKey('n_plus_one_patterns', $analysis);
        $this->assertArrayHasKey('query_types', $analysis);
    }

    /**
     * Test that memory usage is reasonable during Phase 3 operations
     */
    public function testMemoryUsage(): void
    {
        $startMemory = memory_get_usage(true);
        
        $config = new ConfigurationService([
            'laminas_microscope' => [
                'storage' => ['path' => sys_get_temp_dir() . '/lm-test'],
                'enabled' => true
            ]
        ]);
        
        // Instantiate all Phase 3 services
        $reportStorage = new ReportStorage($config);
        $cacheManager = new CacheManager($config);
        $collector = new EnhancedPDOCollector(new ServiceManager());
        
        // Generate some data
        $analytics = [
            'query_analysis' => $reportStorage->getQueryAnalysis(),
            'route_analysis' => $reportStorage->getRouteAnalysis(),
            'performance_data' => $reportStorage->getPerformanceData(),
            'summary' => $reportStorage->getSummary(),
        ];
        
        $cacheStats = $cacheManager->getStats();
        $performanceData = $collector->collect();
        
        $endMemory = memory_get_usage(true);
        $memoryUsed = $endMemory - $startMemory;
        
        // Phase 3 services should use reasonable memory (less than 10MB)
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 
            "Phase 3 services used too much memory: " . number_format($memoryUsed) . " bytes");
    }
}