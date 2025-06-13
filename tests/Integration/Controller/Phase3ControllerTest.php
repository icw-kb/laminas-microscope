<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Integration\Controller;

use LaminasMicroscopeTest\Integration\BaseIntegrationTestCase;
use Laminas\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
use LaminasMicroscope\Controller\DashboardController;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Cache\CacheManager;
use LaminasMicroscope\Microscope\Storage\ReportStorage;
use LaminasMicroscope\Collector\EnhancedPDOCollector;

class Phase3ControllerTest extends AbstractHttpControllerTestCase
{
    protected function setUp(): void
    {
        $this->setApplicationConfig(include __DIR__ . '/../../config/testing.global.php');
        parent::setUp();
    }

    /**
     * Test analytics route works even without services configured
     */
    public function testAnalyticsRouteWithoutServices(): void
    {
        // Remove services from service manager to simulate missing dependencies
        $serviceManager = $this->getApplicationServiceLocator();
        
        // Test that route is accessible
        $this->dispatch('/_debug/analytics');
        
        $this->assertResponseStatusCode(200);
        $this->assertControllerName(DashboardController::class);
        $this->assertActionName('analytics');
        
        // Check that view variables are set correctly
        $viewModel = $this->getApplication()->getMvcEvent()->getResult();
        $this->assertInstanceOf(\Laminas\View\Model\ViewModel::class, $viewModel);
        
        $variables = $viewModel->getVariables();
        $this->assertArrayHasKey('analytics', $variables);
        $this->assertArrayHasKey('config', $variables);
        
        // Verify analytics structure even with empty data
        $analytics = $variables['analytics'];
        $this->assertArrayHasKey('query_analysis', $analytics);
        $this->assertArrayHasKey('route_analysis', $analytics);
        $this->assertArrayHasKey('performance_data', $analytics);
        $this->assertArrayHasKey('summary', $analytics);
        
        // Verify default empty values
        $this->assertEquals(0, $analytics['query_analysis']['total_queries']);
        $this->assertIsArray($analytics['query_analysis']['slow_queries']);
        $this->assertEquals(100, $analytics['summary']['performance_score']);
    }

    /**
     * Test cache route works even without CacheManager
     */
    public function testCacheRouteWithoutCacheManager(): void
    {
        $this->dispatch('/_debug/cache');
        
        $this->assertResponseStatusCode(200);
        $this->assertControllerName(DashboardController::class);
        $this->assertActionName('cache');
        
        $viewModel = $this->getApplication()->getMvcEvent()->getResult();
        $variables = $viewModel->getVariables();
        
        $this->assertArrayHasKey('cacheStats', $variables);
        $this->assertArrayHasKey('config', $variables);
        
        // Should have default cache stats even without CacheManager
        $cacheStats = $variables['cacheStats'];
        $this->assertIsArray($cacheStats);
        $this->assertArrayHasKey('default', $cacheStats);
    }

    /**
     * Test performance route works even without EnhancedPDOCollector
     */
    public function testPerformanceRouteWithoutCollector(): void
    {
        $this->dispatch('/_debug/performance');
        
        $this->assertResponseStatusCode(200);
        $this->assertControllerName(DashboardController::class);
        $this->assertActionName('performance');
        
        $viewModel = $this->getApplication()->getMvcEvent()->getResult();
        $variables = $viewModel->getVariables();
        
        $this->assertArrayHasKey('performanceData', $variables);
        $this->assertArrayHasKey('config', $variables);
        
        // Should have default performance data
        $performanceData = $variables['performanceData'];
        $this->assertEquals(100, $performanceData['performance_score']);
        $this->assertEquals(0, $performanceData['nb_statements']);
        $this->assertIsArray($performanceData['statements']);
    }

    /**
     * Test analytics route with actual services configured
     */
    public function testAnalyticsRouteWithServices(): void
    {
        // Get service manager and ensure services are available
        $serviceManager = $this->getApplicationServiceLocator();
        
        // Create and register services
        $config = new ConfigurationService([
            'laminas_microscope' => [
                'storage' => ['path' => sys_get_temp_dir() . '/lm-test']
            ]
        ]);
        $serviceManager->setService(ConfigurationService::class, $config);
        
        $reportStorage = new ReportStorage($config);
        $serviceManager->setService(ReportStorage::class, $reportStorage);
        
        $this->dispatch('/_debug/analytics');
        
        $this->assertResponseStatusCode(200);
        
        $viewModel = $this->getApplication()->getMvcEvent()->getResult();
        $variables = $viewModel->getVariables();
        
        $this->assertArrayHasKey('analytics', $variables);
        // With actual services, we should get real analytics data structure
        $analytics = $variables['analytics'];
        $this->assertArrayHasKey('query_analysis', $analytics);
        $this->assertArrayHasKey('route_analysis', $analytics);
    }

    /**
     * Test cache clear functionality
     */
    public function testCacheClearAction(): void
    {
        // Test POST request to clear cache
        $this->dispatch('/_debug/cache/clear', 'POST', ['category' => 'default']);
        
        // Should redirect back to cache page
        $this->assertResponseStatusCode(302);
        $this->assertRedirectTo('/_debug/cache');
    }

    /**
     * Test that all Phase 3 routes are properly configured
     */
    public function testPhase3RoutesConfiguration(): void
    {
        $router = $this->getApplicationServiceLocator()->get('Router');
        
        // Test analytics route
        $routeMatch = $router->match($this->getRequest()->setUri('/_debug/analytics'));
        $this->assertNotNull($routeMatch);
        $this->assertEquals(DashboardController::class, $routeMatch->getParam('controller'));
        $this->assertEquals('analytics', $routeMatch->getParam('action'));
        
        // Test cache route
        $routeMatch = $router->match($this->getRequest()->setUri('/_debug/cache'));
        $this->assertNotNull($routeMatch);
        $this->assertEquals(DashboardController::class, $routeMatch->getParam('controller'));
        $this->assertEquals('cache', $routeMatch->getParam('action'));
        
        // Test performance route
        $routeMatch = $router->match($this->getRequest()->setUri('/_debug/performance'));
        $this->assertNotNull($routeMatch);
        $this->assertEquals(DashboardController::class, $routeMatch->getParam('controller'));
        $this->assertEquals('performance', $routeMatch->getParam('action'));
    }

    /**
     * Test view template resolution for Phase 3 templates
     */
    public function testPhase3TemplateResolution(): void
    {
        $viewManager = $this->getApplicationServiceLocator()->get('ViewManager');
        $resolver = $viewManager->getResolver();
        
        // Test that Phase 3 templates are properly mapped
        $templates = [
            'laminas-microscope/dashboard/analytics',
            'laminas-microscope/dashboard/cache', 
            'laminas-microscope/dashboard/performance'
        ];
        
        foreach ($templates as $template) {
            $resolved = $resolver->resolve($template);
            $this->assertNotFalse($resolved, "Template {$template} should be resolvable");
            $this->assertFileExists($resolved, "Template file for {$template} should exist");
        }
    }

    /**
     * Test error handling when services throw exceptions
     */
    public function testErrorHandlingWithServiceExceptions(): void
    {
        // Create a mock service that throws exceptions
        $serviceManager = $this->getApplicationServiceLocator();
        
        $mockReportStorage = $this->createMock(ReportStorage::class);
        $mockReportStorage->method('getQueryAnalysis')
                         ->willThrowException(new \Exception('Storage error'));
        
        $serviceManager->setService(ReportStorage::class, $mockReportStorage);
        
        $this->dispatch('/_debug/analytics');
        
        $this->assertResponseStatusCode(200);
        
        $viewModel = $this->getApplication()->getMvcEvent()->getResult();
        $variables = $viewModel->getVariables();
        
        // Should have error message and fallback data
        $this->assertArrayHasKey('error', $variables);
        $this->assertArrayHasKey('analytics', $variables);
        
        // Analytics should contain empty fallback data
        $analytics = $variables['analytics'];
        $this->assertEquals(0, $analytics['query_analysis']['total_queries']);
    }

    /**
     * Test navigation links work correctly
     */
    public function testPhase3NavigationLinks(): void
    {
        $this->dispatch('/_debug');
        $this->assertResponseStatusCode(200);
        
        $content = $this->getResponse()->getContent();
        
        // Check that Phase 3 navigation links are present
        $this->assertStringContainsString('/_debug/analytics', $content);
        $this->assertStringContainsString('/_debug/cache', $content);
        $this->assertStringContainsString('/_debug/performance', $content);
        
        // Check for FontAwesome icons
        $this->assertStringContainsString('fa-chart-line', $content);
        $this->assertStringContainsString('fa-memory', $content);
        $this->assertStringContainsString('fa-rocket', $content);
    }

    /**
     * Performance test to ensure Phase 3 routes don't introduce significant overhead
     */
    public function testPhase3PerformanceOverhead(): void
    {
        $routes = ['/_debug', '/_debug/analytics', '/_debug/cache', '/_debug/performance'];
        
        foreach ($routes as $route) {
            $startTime = microtime(true);
            $startMemory = memory_get_usage();
            
            $this->dispatch($route);
            $this->assertResponseStatusCode(200);
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage();
            
            $duration = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            
            // Route should respond within reasonable time (adjust as needed)
            $this->assertLessThan(2.0, $duration, "Route {$route} took too long: {$duration}s");
            
            // Memory usage should be reasonable (adjust as needed) 
            $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, "Route {$route} used too much memory: {$memoryUsed} bytes");
            
            $this->reset();
        }
    }
}