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
        
        $this->controller = new MicroscopeController(
            $this->configService,
            $this->analysisService
        );
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

        $this->assertIsArray($result);
        $this->assertArrayHasKey('analysis', $result);
        $this->assertArrayHasKey('config', $result);
        $this->assertEquals($mockAnalysis, $result['analysis']);
    }

    public function testDashboardActionReturnsViewModel(): void
    {
        $this->analysisService
            ->expects($this->once())
            ->method('getSystemOverview')
            ->willReturn([
                'system' => [
                    'php_version' => '8.1.0',
                    'memory_limit' => '128M',
                ],
                'application' => [
                    'environment' => 'testing',
                    'debug_mode' => true,
                ],
            ]);

        $result = $this->controller->dashboardAction();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('overview', $result);
        $this->assertArrayHasKey('components', $result);
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
        
        $result = $this->controller->profilerAction();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('profiler_data', $result);
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

        $this->assertIsArray($result);
        $this->assertEquals($mockData, $result);
    }

    public function testConfigActionReturnsConfigurationData(): void
    {
        $result = $this->controller->configAction();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('config', $result);
        $this->assertArrayHasKey('environment', $result);
        $this->assertEquals('testing', $result['environment']);
    }

    public function testToolsActionReturnsToolsList(): void
    {
        $result = $this->controller->toolsAction();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('tools', $result);
        $this->assertArrayHasKey('available_analyzers', $result);
    }

    public function testControllerHandlesExceptions(): void
    {
        $this->analysisService
            ->expects($this->once())
            ->method('getCurrentAnalysis')
            ->willThrowException(new \Exception('Test exception'));

        $result = $this->controller->indexAction();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Test exception', $result['error']);
    }
}
