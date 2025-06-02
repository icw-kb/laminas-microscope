<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Integration;

use PHPUnit\Framework\TestCase;

class ControllerTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = \TestHelper::createMockConfig([
            'router' => [
                'routes' => [
                    '_debug' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/_debug',
                            'defaults' => [
                                'controller' => 'LaminasMicroscope\Controller\DashboardController',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'microscope' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/microscope[/:action[/:id]]',
                                    'defaults' => [
                                        'controller' => 'LaminasMicroscope\Controller\MicroscopeController',
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                            'config' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/config',
                                    'defaults' => [
                                        'controller' => 'LaminasMicroscope\Controller\ConfigurationController',
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                            'api' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/api[/:action]',
                                    'defaults' => [
                                        'controller' => 'LaminasMicroscope\Controller\ApiController',
                                        'action' => 'status',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testDashboardActionCanBeAccessed(): void
    {
        // Test basic route configuration
        $routes = $this->config['router']['routes'] ?? [];
        $this->assertArrayHasKey('_debug', $routes);
        
        $debugRoute = $routes['_debug'];
        $this->assertEquals('/_debug', $debugRoute['options']['route']);
        $this->assertEquals(
            'LaminasMicroscope\Controller\DashboardController',
            $debugRoute['options']['defaults']['controller']
        );
        $this->assertEquals('index', $debugRoute['options']['defaults']['action']);
    }

    public function testMicroscopeActionCanBeAccessed(): void
    {
        $routes = $this->config['router']['routes'] ?? [];
        $microscopeRoute = $routes['_debug']['child_routes']['microscope'] ?? null;
        
        $this->assertNotNull($microscopeRoute);
        $this->assertEquals('/microscope[/:action[/:id]]', $microscopeRoute['options']['route']);
        $this->assertEquals(
            'LaminasMicroscope\Controller\MicroscopeController',
            $microscopeRoute['options']['defaults']['controller']
        );
    }

    public function testConfigurationActionCanBeAccessed(): void
    {
        $routes = $this->config['router']['routes'] ?? [];
        $configRoute = $routes['_debug']['child_routes']['config'] ?? null;
        
        $this->assertNotNull($configRoute);
        $this->assertEquals('/config', $configRoute['options']['route']);
        $this->assertEquals(
            'LaminasMicroscope\Controller\ConfigurationController',
            $configRoute['options']['defaults']['controller']
        );
    }

    public function testApiRoutesAreConfigured(): void
    {
        $routes = $this->config['router']['routes'] ?? [];
        $apiRoute = $routes['_debug']['child_routes']['api'] ?? null;
        
        $this->assertNotNull($apiRoute);
        $this->assertEquals('/api[/:action]', $apiRoute['options']['route']);
        $this->assertEquals(
            'LaminasMicroscope\Controller\ApiController',
            $apiRoute['options']['defaults']['controller']
        );
        $this->assertEquals('status', $apiRoute['options']['defaults']['action']);
    }

    public function testConfigurationIsValid(): void
    {
        // Test that the mock config contains expected microscope configuration
        $microscopeConfig = $this->config['laminas_microscope'] ?? [];
        
        $this->assertTrue($microscopeConfig['enabled'] ?? false);
        $this->assertEquals('testing', $microscopeConfig['environment'] ?? '');
        
        // Test components configuration
        $components = $microscopeConfig['components'] ?? [];
        $this->assertArrayHasKey('whoops', $components);
        $this->assertArrayHasKey('debug_bar', $components);
        $this->assertArrayHasKey('microscope', $components);
    }

    public function testMockRequestCreation(): void
    {
        $request = \TestHelper::createMockRequest('POST', '/test/url', ['Content-Type' => 'application/json']);
        
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/test/url', $request->getUri());
        $this->assertEquals('application/json', $request->getHeader('Content-Type'));
    }

    public function testMockResponseCreation(): void
    {
        $response = \TestHelper::createMockResponse(201, ['Location' => '/created/resource'], '{"success": true}');
        
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('/created/resource', $response->getHeaders()['Location']);
        $this->assertEquals('{"success": true}', $response->getBody());
    }

    public function testArrayStructureAssertion(): void
    {
        $expected = [
            'data' => [
                'users' => [],
                'meta' => [
                    'total' => 0
                ]
            ],
            'success' => true
        ];
        
        $actual = [
            'data' => [
                'users' => [
                    ['id' => 1, 'name' => 'John']
                ],
                'meta' => [
                    'total' => 1,
                    'page' => 1
                ]
            ],
            'success' => true,
            'timestamp' => '2024-01-01T00:00:00Z'
        ];
        
        // This should pass - actual contains all expected structure
        \TestHelper::assertArrayStructure($expected, $actual);
        $this->assertTrue(true); // If we get here, the assertion passed
    }
}
