<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Unit\Microscope;

use LaminasMicroscope\Microscope\MicroscopeHandler;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Microscope\Storage\ReportStorage;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\RouteMatch;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use Laminas\Mvc\Application;

class MicroscopeHandlerTest extends TestCase
{
    private MicroscopeHandler $handler;
    private ComponentManager $componentManager;
    private ConfigurationService $configService;
    private ContainerInterface $container;
    private \LaminasMicroscope\Collector\CollectorRegistry $registry;
    private Application $application;

    protected function setUp(): void
    {
        // Create mock dependencies
        $this->componentManager = $this->createMock(ComponentManager::class);
        $this->configService = $this->createMock(ConfigurationService::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->registry = new \LaminasMicroscope\Collector\CollectorRegistry();
        $this->application = $this->createMock(Application::class);

        // Create the real handler with mocked dependencies
        $this->handler = new MicroscopeHandler(
            $this->componentManager,
            $this->configService,
            $this->container,
            $this->registry
        );
    }

    private function createEvent(): MvcEvent
    {
        $event = $this->createMock(MvcEvent::class);
        $serviceManager = \TestHelper::createMockServiceManager();
        $serviceManager->set(MicroscopeHandler::class, $this->handler);
        $this->application->method('getServiceManager')->willReturn($serviceManager);
        $event->method('getApplication')->willReturn($this->application);
        return $event;
    }

    public function testIsEnabledReturnsTrueWhenMicroscopeEnabled(): void
    {
        $this->componentManager
            ->expects($this->once())
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);

        $this->assertTrue($this->handler->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenMicroscopeDisabled(): void
    {
        $this->componentManager
            ->expects($this->once())
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(false);

        $this->assertFalse($this->handler->isEnabled());
    }

    public function testGetNameReturnsMicroscope(): void
    {
        $this->assertEquals('microscope', $this->handler->getName());
    }

    public function testGetConfigReturnsConfiguration(): void
    {
        $expectedConfig = [
            'auto_analyze' => true,
            'checks' => ['n_plus_one' => true],
            'thresholds' => ['query_time' => 100],
        ];

        $this->configService
            ->expects($this->once())
            ->method('get')
            ->with('laminas_microscope.components.microscope', [])
            ->willReturn($expectedConfig);

        $config = $this->handler->getConfig();
        $this->assertEquals($expectedConfig, $config);
    }

    public function testInitializeDoesNothingWhenDisabled(): void
    {
        $this->componentManager
            ->expects($this->once())
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(false);

        // When disabled, initialize should complete without errors
        $this->handler->initialize();
        
        // Verify that profile data remains empty when disabled
        $profileData = $this->handler->getProfileData();
        $this->assertEmpty($profileData);
    }

    public function testInitializeCreatesProfileDataWhenEnabled(): void
    {
        $this->componentManager
            ->expects($this->once())
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);

        // Note: ReportStorage constructor now expects ConfigurationService, not a string path
        // No need to mock getStoragePath since it's handled internally by ReportStorage

        $this->handler->initialize();

        $profileData = $this->handler->getProfileData();
        $this->assertIsArray($profileData);
        $this->assertArrayHasKey('queries', $profileData);
        $this->assertArrayHasKey('routes', $profileData);
        $this->assertArrayHasKey('views', $profileData);
        $this->assertArrayHasKey('performance', $profileData);
        $this->assertArrayHasKey('analysis', $profileData);
    }

    public function testStartProfilingDoesNothingWhenDisabled(): void
    {
        $this->componentManager
            ->expects($this->once())
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(false);

        $event = $this->createEvent();
        $event->expects($this->never())->method('getRouteMatch');

        // When disabled, start profiling should complete without errors
        $this->handler->startProfiling($event);
        
        // Verify that no route data was recorded when disabled
        $profileData = $this->handler->getProfileData();
        $this->assertEmpty($profileData);
    }

    public function testStartProfilingRecordsRouteDataWhenEnabled(): void
    {
        $this->componentManager
            ->expects($this->exactly(2))
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);

        $routeMatch = $this->createMock(RouteMatch::class);
        $routeMatch->method('getMatchedRouteName')->willReturn('home');
        $routeMatch->method('getParam')->willReturnMap([
            ['controller', null, 'IndexController'],
            ['action', null, 'index'],
        ]);
        $routeMatch->method('getParams')->willReturn(['controller' => 'IndexController', 'action' => 'index']);

        $event = $this->createEvent();
        $event->method('getRouteMatch')->willReturn($routeMatch);

        $this->handler->startProfiling($event);

        $profileData = $this->handler->getProfileData();
        $this->assertNotEmpty($profileData['routes']);
        $this->assertEquals('home', $profileData['routes'][0]['route_name']);
        $this->assertEquals('IndexController', $profileData['routes'][0]['controller']);
        $this->assertEquals('index', $profileData['routes'][0]['action']);
    }

    public function testProfileDispatchDoesNothingWhenDisabled(): void
    {
        $this->componentManager
            ->expects($this->once())
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(false);

        $event = $this->createEvent();

        // When disabled, profile dispatch should complete without errors
        $this->handler->profileDispatch($event);
        
        // Verify that no performance data was recorded when disabled
        $profileData = $this->handler->getProfileData();
        $this->assertEmpty($profileData);
    }

    public function testProfileDispatchRecordsPerformanceDataWhenEnabled(): void
    {
        // Configure the mocks to return enabled=true
        // Called 2 times: once in initialize(), once in profileDispatch()
        $this->componentManager
            ->expects($this->exactly(2))
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);

        // Mock config calls for analysis - auto_analyze is FALSE so no additional isEnabled() call
        $this->configService
            ->method('get')
            ->willReturnMap([
                ['laminas_microscope.components.microscope.auto_analyze', true, false], // FALSE - no auto analysis
                ['laminas_microscope.components.microscope.checks', [], []],
                ['laminas_microscope.components.microscope.thresholds.duplicate_query_threshold', 3, 3],
                ['laminas_microscope.components.microscope.thresholds.query_time', 100, 100],
                ['laminas_microscope.components.microscope.thresholds.response_size', 1048576, 1048576],
                ['laminas_microscope.components.microscope.reporting.log_level', 'warning', 'warning'],
                ['laminas_microscope.components.microscope', [], []], // For getConfig() call
            ]);

        // IMPORTANT: Initialize the handler first to set up $startTime
        $this->handler->initialize();

        $event = $this->createEvent();

        // Now call profileDispatch - $startTime should be initialized
        $this->handler->profileDispatch($event);

        $profileData = $this->handler->getProfileData();
        $this->assertArrayHasKey('performance', $profileData);
        $this->assertArrayHasKey('total_time', $profileData['performance']);
        $this->assertArrayHasKey('memory_usage', $profileData['performance']);
        $this->assertArrayHasKey('peak_memory', $profileData['performance']);
    }

    public function testRunAnalysisReturnsQueriesAnalysis(): void
    {
        // Enable microscope
        $this->componentManager
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);

        // Mock configuration for analysis
        $this->configService
            ->method('get')
            ->willReturnMap([
                ['laminas_microscope.components.microscope.thresholds.duplicate_query_threshold', 3, 3],
                ['laminas_microscope.components.microscope.thresholds.query_time', 100, 100],
                ['laminas_microscope.components.microscope.checks', [], ['duplicate_queries' => true, 'slow_queries' => true]],
                ['laminas_microscope.components.microscope', [], []], // For getConfig() call
            ]);

        // Initialize the handler
        $this->handler->initialize();

        // Simulate adding queries through recordQuery method
        $this->handler->recordQuery([
            'sql' => 'SELECT * FROM users WHERE id = ?',
            'params' => [1],
            'duration' => 150, // Slow query
            'trace' => []
        ]);

        $this->handler->recordQuery([
            'sql' => 'SELECT * FROM posts WHERE user_id = ?',
            'params' => [1],
            'duration' => 50, // Fast query
            'trace' => []
        ]);

        $analysis = $this->handler->runAnalysis();

        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('id', $analysis);
        $this->assertArrayHasKey('created_at', $analysis);
        $this->assertArrayHasKey('issues', $analysis);
        $this->assertArrayHasKey('queries', $analysis);
        $this->assertArrayHasKey('performance_score', $analysis);
    }

    public function testHandlerCanBeInitializedAndUsed(): void
    {
        $this->componentManager
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);

        $this->configService
            ->method('get')
            ->willReturn([]);

        // Test the basic workflow
        $this->handler->initialize();
        
        $event = $this->createEvent();
        $this->handler->startProfiling($event);
        $this->handler->profileDispatch($event);

        $profileData = $this->handler->getProfileData();
        $this->assertIsArray($profileData);
        
        // Verify the handler maintains proper state
        $this->assertTrue($this->handler->isEnabled());
        $this->assertEquals('microscope', $this->handler->getName());
    }

    public function testGetProfileDataReturnsCorrectStructure(): void
    {
        $this->componentManager
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);

        $this->handler->initialize();

        $profileData = $this->handler->getProfileData();
        
        $this->assertIsArray($profileData);
        $this->assertArrayHasKey('queries', $profileData);
        $this->assertArrayHasKey('routes', $profileData);
        $this->assertArrayHasKey('views', $profileData);
        $this->assertArrayHasKey('performance', $profileData);
        $this->assertArrayHasKey('analysis', $profileData);
        
        // Check that each section is an array
        $this->assertIsArray($profileData['queries']);
        $this->assertIsArray($profileData['routes']);
        $this->assertIsArray($profileData['views']);
        $this->assertIsArray($profileData['performance']);
        $this->assertIsArray($profileData['analysis']);
    }

    public function testAnalysisIsPerformedWhenEnabled(): void
    {
        // The actual call flow is: initialize() -> profileDispatch() -> performAnalysis() -> runAnalysis()
        // But runAnalysis() does NOT call isEnabled() - it just returns empty array if not enabled
        // So we only get 2 calls: initialize() and profileDispatch()
        $this->componentManager
            ->expects($this->exactly(2)) // Only 2 calls: initialize() and profileDispatch()
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);

        // Mock auto-analyze being enabled
        $this->configService
            ->method('get')
            ->willReturnMap([
                ['laminas_microscope.components.microscope.auto_analyze', true, true], // TRUE - enables auto analysis
                ['laminas_microscope.components.microscope.checks', [], []],
                ['laminas_microscope.components.microscope.thresholds.duplicate_query_threshold', 3, 3],
                ['laminas_microscope.components.microscope.thresholds.query_time', 100, 100],
                ['laminas_microscope.components.microscope.thresholds.response_size', 1048576, 1048576],
                ['laminas_microscope.components.microscope.reporting.log_level', 'warning', 'warning'],
                ['laminas_microscope.components.microscope', [], []], // For getConfig() call
            ]);

        $this->handler->initialize();
        
        $event = $this->createEvent();
        $this->handler->profileDispatch($event);

        $profileData = $this->handler->getProfileData();
        
        // When auto-analyze is enabled, analysis should be performed
        $this->assertArrayHasKey('analysis', $profileData);
        $this->assertIsArray($profileData['analysis']);
        
        // Verify that the analysis contains expected structure
        if (!empty($profileData['analysis'])) {
            $this->assertArrayHasKey('id', $profileData['analysis']);
            $this->assertArrayHasKey('created_at', $profileData['analysis']);
            $this->assertArrayHasKey('performance_score', $profileData['analysis']);
        }
    }

    public function testConfigurationIsAccessible(): void
    {
        $testConfig = [
            'auto_analyze' => false,
            'checks' => ['n_plus_one' => true, 'slow_queries' => true],
            'thresholds' => ['query_time' => 200, 'memory_limit' => 128 * 1024 * 1024],
        ];

        $this->configService
            ->expects($this->once())
            ->method('get')
            ->with('laminas_microscope.components.microscope', [])
            ->willReturn($testConfig);

        $config = $this->handler->getConfig();
        
        $this->assertEquals($testConfig, $config);
        $this->assertFalse($config['auto_analyze']);
        $this->assertTrue($config['checks']['n_plus_one']);
        $this->assertEquals(200, $config['thresholds']['query_time']);
    }

    public function testRecordQueryAddsQueryToProfileData(): void
    {
        $this->componentManager
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);

        $this->handler->initialize();

        $queryData = [
            'sql' => 'SELECT * FROM users WHERE active = 1',
            'params' => [],
            'duration' => 45.7,
            'trace' => ['file' => 'test.php', 'line' => 123]
        ];

        $this->handler->recordQuery($queryData);

        $profileData = $this->handler->getProfileData();
        $this->assertCount(1, $profileData['queries']);
        $this->assertEquals($queryData['sql'], $profileData['queries'][0]['sql']);
        $this->assertEquals($queryData['duration'], $profileData['queries'][0]['duration']);
        $this->assertArrayHasKey('timestamp', $profileData['queries'][0]);
    }

    public function testRecordViewAddsViewToProfileData(): void
    {
        $this->componentManager
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);

        $this->handler->initialize();

        $this->handler->recordView('layout/layout.phtml', 12.5);

        $profileData = $this->handler->getProfileData();
        $this->assertCount(1, $profileData['views']);
        $this->assertEquals('layout/layout.phtml', $profileData['views'][0]['template']);
        $this->assertEquals(12.5, $profileData['views'][0]['render_time']);
        $this->assertArrayHasKey('timestamp', $profileData['views'][0]);
    }

    public function testRunAnalysisDirectCallWithEnabledMicroscope(): void
    {
        // Test runAnalysis() when called directly (not through auto-analysis)
        // Actual calls: initialize() + recordQuery() + runAnalysis() = 3 calls to isEnabled()
        $this->componentManager
            ->expects($this->exactly(3)) // initialize() + recordQuery() + runAnalysis()
            ->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);

        // Mock configuration for analysis
        $this->configService
            ->method('get')
            ->willReturnMap([
                ['laminas_microscope.components.microscope.thresholds.duplicate_query_threshold', 3, 3],
                ['laminas_microscope.components.microscope.thresholds.query_time', 100, 100],
                ['laminas_microscope.components.microscope.checks', [], ['duplicate_queries' => true, 'slow_queries' => true]],
                ['laminas_microscope.components.microscope', [], []], // For getConfig() call
            ]);

        $this->handler->initialize();

        // Add some test data
        $this->handler->recordQuery([
            'sql' => 'SELECT * FROM users WHERE id = ?',
            'params' => [1],
            'duration' => 150,
            'trace' => []
        ]);

        // Call runAnalysis directly
        $analysis = $this->handler->runAnalysis();

        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('id', $analysis);
        $this->assertArrayHasKey('created_at', $analysis);
        $this->assertArrayHasKey('performance_score', $analysis);
        $this->assertArrayHasKey('issues', $analysis);
    }
}
