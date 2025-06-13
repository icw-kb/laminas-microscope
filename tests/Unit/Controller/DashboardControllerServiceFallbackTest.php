<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Unit\Controller;

use LaminasMicroscopeTest\Unit\BaseTestCase;
use LaminasMicroscope\Controller\DashboardController;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Manager\ComponentManager;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Mvc\Controller\PluginManager;
use Laminas\View\Model\ViewModel;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Http\Request;
use Laminas\Mvc\Controller\Plugin\FlashMessenger;
use Laminas\Mvc\Controller\Plugin\Redirect;

class DashboardControllerServiceFallbackTest extends BaseTestCase
{
    private DashboardController $controller;
    private ServiceManager $serviceManager;
    private ConfigurationService $config;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->config = new ConfigurationService([
            'laminas_microscope' => [
                'storage' => ['path' => sys_get_temp_dir() . '/lm-test'],
                'enabled' => true
            ]
        ]);
        
        $componentManager = $this->createMock(ComponentManager::class);
        
        $this->serviceManager = new ServiceManager();
        $this->serviceManager->setService(ConfigurationService::class, $this->config);
        
        $this->controller = new DashboardController($componentManager, $this->config);
        
        // Mock request
        $request = new Request();
        $this->controller->setRequest($request);
        
        // Mock plugin manager with necessary plugins
        $pluginManager = $this->createMock(PluginManager::class);
        
        // Mock params plugin
        $params = $this->createMock(Params::class);
        $params->method('fromRoute')->willReturn('index');
        $params->method('fromPost')->willReturn('default');
        
        // Mock flash messenger
        $flashMessenger = $this->createMock(FlashMessenger::class);
        
        // Mock redirect plugin
        $redirect = $this->createMock(Redirect::class);
        $redirect->method('toRoute')->willReturn($this->createMock(\Laminas\Http\Response::class));
        
        $pluginManager->method('get')->willReturnMap([
            ['params', null, $params],
            ['flashMessenger', null, $flashMessenger],
            ['redirect', null, $redirect],
        ]);
        
        $this->controller->setPluginManager($pluginManager);
        
        // Set service locator
        $this->controller->setServiceLocator($this->serviceManager);
    }

    /**
     * Test analyticsAction works without ReportStorage service
     */
    public function testAnalyticsActionWithoutReportStorageService(): void
    {
        // Ensure ReportStorage is NOT in service manager
        $this->assertFalse($this->serviceManager->has('LaminasMicroscope\Microscope\Storage\ReportStorage'));
        
        $result = $this->controller->analyticsAction();
        
        $this->assertInstanceOf(ViewModel::class, $result);
        
        $variables = $result->getVariables();
        $this->assertArrayHasKey('analytics', $variables);
        $this->assertArrayHasKey('config', $variables);
        
        // Should have proper structure even without service
        $analytics = $variables['analytics'];
        $this->assertArrayHasKey('query_analysis', $analytics);
        $this->assertArrayHasKey('route_analysis', $analytics);
        $this->assertArrayHasKey('performance_data', $analytics);
        $this->assertArrayHasKey('summary', $analytics);
        
        // Verify default values
        $this->assertEquals(0, $analytics['query_analysis']['total_queries']);
        $this->assertIsArray($analytics['query_analysis']['slow_queries']);
        $this->assertIsArray($analytics['query_analysis']['duplicate_queries']);
        $this->assertIsArray($analytics['query_analysis']['n_plus_one_patterns']);
        
        $this->assertEquals(0, $analytics['route_analysis']['total_requests']);
        $this->assertIsArray($analytics['route_analysis']['popular_routes']);
        
        $this->assertEquals(0, $analytics['performance_data']['total_requests']);
        $this->assertEquals(0, $analytics['performance_data']['average_response_time']);
        
        $this->assertEquals(100, $analytics['summary']['performance_score']);
        $this->assertIsArray($analytics['summary']['recommendations']);
    }

    /**
     * Test cacheAction works without CacheManager service
     */
    public function testCacheActionWithoutCacheManagerService(): void
    {
        $this->assertFalse($this->serviceManager->has('LaminasMicroscope\Cache\CacheManager'));
        
        $result = $this->controller->cacheAction();
        
        $this->assertInstanceOf(ViewModel::class, $result);
        
        $variables = $result->getVariables();
        $this->assertArrayHasKey('cacheStats', $variables);
        $this->assertArrayHasKey('config', $variables);
        
        // Should have default cache stats structure
        $cacheStats = $variables['cacheStats'];
        $this->assertIsArray($cacheStats);
        $this->assertArrayHasKey('default', $cacheStats);
        
        $defaultStats = $cacheStats['default'];
        $this->assertEquals('FileAdapter', $defaultStats['type']);
        $this->assertArrayHasKey('stats', $defaultStats);
        
        $stats = $defaultStats['stats'];
        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(0, $stats['misses']);
        $this->assertEquals(0, $stats['writes']);
        $this->assertEquals(0, $stats['deletes']);
    }

    /**
     * Test performanceAction works without EnhancedPDOCollector service
     */
    public function testPerformanceActionWithoutEnhancedPDOCollectorService(): void
    {
        $this->assertFalse($this->serviceManager->has('LaminasMicroscope\Collector\EnhancedPDOCollector'));
        
        $result = $this->controller->performanceAction();
        
        $this->assertInstanceOf(ViewModel::class, $result);
        
        $variables = $result->getVariables();
        $this->assertArrayHasKey('performanceData', $variables);
        $this->assertArrayHasKey('config', $variables);
        
        // Should have default performance data structure
        $performanceData = $variables['performanceData'];
        $this->assertEquals(100, $performanceData['performance_score']);
        $this->assertEquals(0, $performanceData['nb_statements']);
        $this->assertEquals(0, $performanceData['nb_slow_statements']);
        $this->assertEquals(0, $performanceData['nb_duplicate_statements']);
        $this->assertEquals('0ms', $performanceData['accumulated_duration_str']);
        
        $this->assertArrayHasKey('analysis', $performanceData);
        $this->assertIsArray($performanceData['analysis']['n_plus_one_patterns']);
        $this->assertIsArray($performanceData['analysis']['query_types']);
        
        $this->assertIsArray($performanceData['recommendations']);
        $this->assertIsArray($performanceData['statements']);
    }

    /**
     * Test that service exceptions are handled gracefully
     */
    public function testServiceExceptionHandling(): void
    {
        // Create a mock service that throws exception
        $mockReportStorage = $this->createMock('LaminasMicroscope\Microscope\Storage\ReportStorage');
        $mockReportStorage->method('getQueryAnalysis')
                         ->willThrowException(new \RuntimeException('Storage error'));
        
        $this->serviceManager->setService('LaminasMicroscope\Microscope\Storage\ReportStorage', $mockReportStorage);
        
        $result = $this->controller->analyticsAction();
        
        $this->assertInstanceOf(ViewModel::class, $result);
        
        $variables = $result->getVariables();
        $this->assertArrayHasKey('analytics', $variables);
        $this->assertArrayHasKey('error', $variables);
        
        // Should still have fallback analytics data
        $analytics = $variables['analytics'];
        $this->assertEquals(0, $analytics['query_analysis']['total_queries']);
        
        // Should have error message
        $this->assertStringContains('Storage error', $variables['error']);
    }

    /**
     * Test that proper data types are maintained in fallback scenarios
     */
    public function testFallbackDataTypes(): void
    {
        $result = $this->controller->analyticsAction();
        $analytics = $result->getVariable('analytics');
        
        // Verify all expected keys exist and have correct types
        $this->assertIsInt($analytics['query_analysis']['total_queries']);
        $this->assertIsArray($analytics['query_analysis']['slow_queries']);
        $this->assertIsArray($analytics['query_analysis']['duplicate_queries']);
        $this->assertIsArray($analytics['query_analysis']['n_plus_one_patterns']);
        
        $this->assertIsInt($analytics['route_analysis']['total_requests']);
        $this->assertIsArray($analytics['route_analysis']['popular_routes']);
        
        $this->assertIsInt($analytics['performance_data']['total_requests']);
        $this->assertIsNumeric($analytics['performance_data']['average_response_time']);
        
        $this->assertIsInt($analytics['summary']['performance_score']);
        $this->assertIsArray($analytics['summary']['recommendations']);
    }

    /**
     * Test memory usage and performance of fallback scenarios
     */
    public function testFallbackPerformance(): void
    {
        $startMemory = memory_get_usage();
        $startTime = microtime(true);
        
        // Execute all actions without services
        $this->controller->analyticsAction();
        $this->controller->cacheAction();
        $this->controller->performanceAction();
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        
        // Fallback should be fast and memory-efficient
        $this->assertLessThan(0.1, $duration, 'Fallback actions should be fast');
        $this->assertLessThan(5 * 1024 * 1024, $memoryUsed, 'Fallback should use minimal memory');
    }

    /**
     * Test that configuration is always available in view models
     */
    public function testConfigurationAlwaysAvailable(): void
    {
        $actions = ['analyticsAction', 'cacheAction', 'performanceAction'];
        
        foreach ($actions as $action) {
            $result = $this->controller->$action();
            $this->assertInstanceOf(ViewModel::class, $result);
            
            $variables = $result->getVariables();
            $this->assertArrayHasKey('config', $variables);
            $this->assertInstanceOf(ConfigurationService::class, $variables['config']);
        }
    }
}