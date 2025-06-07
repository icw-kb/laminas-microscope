<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Integration\Controller;

use LaminasMicroscope\Controller\MicroscopeController;
use LaminasMicroscope\Service\AnalysisService;
use LaminasMicroscope\Config\ConfigurationService;
use PHPUnit\Framework\TestCase;

class MicroscopeControllerTest extends TestCase
{
    private MicroscopeController $controller;
    private ConfigurationService $configService;
    private AnalysisService $analysisService;

    protected function setUp(): void
    {
        $config = \TestHelper::createMockConfig();
        $this->configService = new ConfigurationService($config);

        $this->analysisService = $this->createMock(AnalysisService::class);
        $componentManager = $this->createMock(\LaminasMicroscope\Manager\ComponentManager::class);
        $microscopeHandler = $this->createMock(\LaminasMicroscope\Microscope\MicroscopeHandler::class);

        $this->controller = new MicroscopeController(
            $this->configService,
            $this->analysisService,
            $componentManager,
            $microscopeHandler
        );

        $event = new \Laminas\Mvc\MvcEvent();
        $event->setRouteMatch(new \Laminas\Router\RouteMatch([]));
        $this->controller->setEvent($event);
    }

    public function testIndexActionReturnsExpectedStructure(): void
    {
        // Mock analysis data
        $mockAnalysis = [
            'performance' => [
                'memory_usage' => 1024000,
                'execution_time' => 0.5,
                'queries_count' => 3,
            ],
            'database' => [
                'queries' => [
                    ['sql' => 'SELECT * FROM users', 'time' => 0.01],
                    ['sql' => 'SELECT * FROM posts', 'time' => 0.02],
                ],
            ],
        ];

        $this->analysisService
            ->expects($this->once())
            ->method('getCurrentAnalysis')
            ->willReturn($mockAnalysis);

        $result = $this->controller->indexAction();

        $this->assertInstanceOf(\Laminas\View\Model\ViewModel::class, $result);
        $vars = $result->getVariables();
        $this->assertArrayHasKey('analysisData', $vars);
        $this->assertArrayHasKey('config', $vars);
        $this->assertEquals($mockAnalysis, $vars['analysisData']);
    }

    public function testDashboardActionReturnsViewModel(): void
    {
        $result = $this->controller->dashboardAction();

        $this->assertInstanceOf(\Laminas\View\Model\ViewModel::class, $result);
        $vars = $result->getVariables();
        $this->assertArrayHasKey('message', $vars);
    }

    public function testProfilerActionHandlesValidRequest(): void
    {
        $request = \TestHelper::createMockRequest('GET', '/microscope/profiler');
        
        $this->analysisService
            ->expects($this->once())
            ->method('getProfilerData')
            ->with('current')
            ->willReturn([
                'timeline' => [],
                'queries' => [],
                'memory' => [],
            ]);

        // Simulate setting request on controller if method exists
        if (method_exists($this->controller, 'setRequest')) {
            $this->controller->setRequest($request);
        }

        $this->controller->getEvent()->setRouteMatch(new \Laminas\Router\RouteMatch(['id' => 'current']));
        
        $result = $this->controller->profilerAction();

        $this->assertInstanceOf(\Laminas\View\Model\ViewModel::class, $result);
        $vars = $result->getVariables();
        $this->assertArrayHasKey('profiler_data', $vars);
    }

    public function testAnalysisActionReturnsJsonResponse(): void
    {
        $mockData = [
            'routes' => [
                ['name' => 'home', 'path' => '/', 'hits' => 150],
                ['name' => 'about', 'path' => '/about', 'hits' => 75],
            ],
            'controllers' => [
                ['name' => 'IndexController', 'actions' => 3],
                ['name' => 'UserController', 'actions' => 5],
            ],
        ];

        $this->analysisService
            ->expects($this->once())
            ->method('getDetailedAnalysis')
            ->willReturn($mockData);

        $result = $this->controller->analysisAction();

        $this->assertInstanceOf(\Laminas\View\Model\JsonModel::class, $result);
        $vars = $result->getVariables();
        $this->assertEquals($mockData, $vars);
    }

    public function testConfigActionReturnsConfigurationData(): void
    {
        $result = $this->controller->configAction();

        $this->assertInstanceOf(\Laminas\View\Model\ViewModel::class, $result);
        $vars = $result->getVariables();
        $this->assertArrayHasKey('message', $vars);
    }

    public function testToolsActionReturnsToolsList(): void
    {
        $result = $this->controller->toolsAction();

        $this->assertInstanceOf(\Laminas\View\Model\ViewModel::class, $result);
        $vars = $result->getVariables();
        $this->assertArrayHasKey('message', $vars);
    }

    public function testControllerHandlesExceptions(): void
    {
        $this->analysisService
            ->expects($this->once())
            ->method('getCurrentAnalysis')
            ->willThrowException(new \Exception('Test exception'));

        $result = $this->controller->indexAction();

        $this->assertInstanceOf(\Laminas\View\Model\ViewModel::class, $result);
        $vars = $result->getVariables();
        $this->assertArrayHasKey('error', $vars);
        $this->assertStringContainsString('Test exception', $vars['error']);
    }
}
