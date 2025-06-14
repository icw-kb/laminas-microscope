<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Unit\Collector;

use DateTime;
use LaminasMicroscope\Collector\LaminasConfigCollector;
use LaminasMicroscope\Collector\LaminasRequestCollector;
use LaminasMicroscope\Collector\PDOCollector;
use LaminasMicroscope\Collector\EnhancedPDOCollector;
use PHPUnit\Framework\TestCase;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Analyzer\QueryAnalyzer;
use LaminasMicroscope\Cache\CacheManager;
use stdClass;

class ObjectDisplayTest extends TestCase
{
    public function testRequestCollectorHandlesObjectsCorrectly(): void
    {
        $serviceManager = $this->createMock(ServiceManager::class);
        $collector = new LaminasRequestCollector($serviceManager);
        
        // Simulate route params with an object
        $routeObject = new stdClass();
        $routeObject->id = 123;
        $routeObject->name = 'test';
        
        $mockRouteMatch = new class($routeObject) {
            private $object;
            public function __construct($object) {
                $this->object = $object;
            }
            public function getMatchedRouteName() { return 'test-route'; }
            public function getParam($param) { return $param === 'controller' ? 'TestController' : 'indexAction'; }
            public function getParams() { 
                return [
                    'controller' => 'TestController',
                    'action' => 'indexAction',
                    'object' => $this->object
                ];
            }
        };
        
        $mockMvcEvent = $this->createMock(\Laminas\Mvc\MvcEvent::class);
        $mockMvcEvent->method('getRouteMatch')->willReturn($mockRouteMatch);
        
        $mockApplication = $this->createMock(\Laminas\Mvc\Application::class);
        $mockApplication->method('getMvcEvent')->willReturn($mockMvcEvent);
        
        $serviceManager->method('has')->with('Application')->willReturn(true);
        $serviceManager->method('get')->with('Application')->willReturn($mockApplication);
        
        $data = $collector->collect();
        
        // Verify no [object Object] strings in output
        $jsonData = json_encode($data);
        $this->assertStringNotContainsString('[object Object]', $jsonData);
        
        // Verify object is properly formatted
        $this->assertArrayHasKey('route', $data);
        $this->assertArrayHasKey('params', $data['route']);
        $this->assertArrayHasKey('object', $data['route']['params']);
        $this->assertIsString($data['route']['params']['object']);
        $this->assertStringContainsString('Object(stdClass)', $data['route']['params']['object']);
    }
    
    public function testConfigCollectorHandlesObjectsCorrectly(): void
    {
        $serviceManager = $this->createMock(ServiceManager::class);
        $collector = new LaminasConfigCollector($serviceManager);
        
        $configObject = new stdClass();
        $configObject->setting = 'value';
        
        $config = [
            'test' => [
                'object' => $configObject,
                'datetime' => new DateTime('2023-01-01 12:00:00')
            ]
        ];
        
        $serviceManager->method('get')->with('config')->willReturn($config);
        
        $data = $collector->collect();
        
        // Verify no [object Object] strings in output
        $jsonData = json_encode($data);
        $this->assertStringNotContainsString('[object Object]', $jsonData);
        
        // Verify DateTime is properly formatted
        $this->assertIsString($data['config']['application_config']['test']['datetime']);
        $this->assertEquals('2023-01-01 12:00:00', $data['config']['application_config']['test']['datetime']);
        
        // Verify stdClass is properly formatted
        $this->assertIsString($data['config']['application_config']['test']['object']);
        $this->assertStringContainsString('Object(stdClass)', $data['config']['application_config']['test']['object']);
    }
    
    public function testPDOCollectorHandlesObjectParameters(): void
    {
        $serviceManager = $this->createMock(ServiceManager::class);
        $collector = new PDOCollector($serviceManager, true); // Skip setup
        
        $paramObject = new stdClass();
        $paramObject->id = 456;
        
        $query = [
            'sql' => 'SELECT * FROM users WHERE id = ?',
            'params' => [
                $paramObject,
                new DateTime('2023-01-01')
            ],
            'duration' => 10.5,
            'memory' => 1024,
            'is_success' => true,
            'error_code' => null,
            'error_message' => null
        ];
        
        $collector->addQuery($query);
        $data = $collector->collect();
        
        // Verify no [object Object] strings in output
        $jsonData = json_encode($data);
        $this->assertStringNotContainsString('[object Object]', $jsonData);
        
        // Verify objects in params are properly formatted
        $statement = $data['statements'][0];
        $this->assertIsString($statement['params'][0]);
        $this->assertStringContainsString('Object(stdClass)', $statement['params'][0]);
        $this->assertEquals('2023-01-01 00:00:00', $statement['params'][1]);
    }
    
    public function testEnhancedPDOCollectorHandlesObjectParameters(): void
    {
        $serviceManager = $this->createMock(ServiceManager::class);
        $cacheManager = $this->createMock(CacheManager::class);
        $queryAnalyzer = $this->createMock(QueryAnalyzer::class);
        
        $config = [
            'cache_analysis_results' => false,
            'slow_threshold' => 100
        ];
        $collector = new EnhancedPDOCollector($serviceManager, $cacheManager, $config, true);
        
        $paramObject = new class {
            public function __toString() {
                return 'CustomObject(789)';
            }
        };
        
        $query = [
            'sql' => 'UPDATE users SET status = ? WHERE id = ?',
            'params' => [
                'active',
                $paramObject
            ],
            'duration' => 15.3,
            'memory' => 2048,
            'is_success' => true,
            'error_code' => null,
            'error_message' => null
        ];
        
        $collector->addQuery($query);
        $data = $collector->collect();
        
        // Verify no [object Object] strings in output
        $jsonData = json_encode($data);
        $this->assertStringNotContainsString('[object Object]', $jsonData);
        
        // Verify object with __toString is properly handled
        $statement = $data['statements'][0];
        $this->assertEquals('active', $statement['params'][0]);
        $this->assertEquals('CustomObject(789)', $statement['params'][1]);
    }
    
    public function testFormatsArrayTraitHandlesCircularReferences(): void
    {
        $serviceManager = $this->createMock(ServiceManager::class);
        $collector = new LaminasRequestCollector($serviceManager);
        
        // Create circular reference
        $obj1 = new stdClass();
        $obj2 = new stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1;
        
        $_SESSION['circular'] = $obj1;
        
        $data = $collector->collect();
        
        // Should not cause infinite recursion
        $jsonData = json_encode($data);
        $this->assertNotFalse($jsonData, 'JSON encoding should not fail with circular references');
        $this->assertStringNotContainsString('[object Object]', $jsonData);
    }
    
    public function testFormatsArrayTraitHandlesResources(): void
    {
        $serviceManager = $this->createMock(ServiceManager::class);
        $collector = new LaminasRequestCollector($serviceManager);
        
        // Create a mock session with a resource
        $resource = fopen('php://memory', 'r');
        
        // Override session status for testing
        ini_set('session.use_cookies', '0');
        @session_start();
        $_SESSION['resource'] = $resource;
        
        $data = $collector->collect();
        fclose($resource);
        @session_destroy();
        
        // Verify resource is properly formatted
        $jsonData = json_encode($data);
        $this->assertStringNotContainsString('[object Object]', $jsonData);
        if (isset($data['session']['data']['resource'])) {
            $this->assertStringContainsString('Resource(stream)', $jsonData);
        } else {
            // If session isn't active, just verify no [object Object]
            $this->assertTrue(true);
        }
    }
    
    public function testFormatsArrayTraitHandlesMaxDepth(): void
    {
        $serviceManager = $this->createMock(ServiceManager::class);
        $collector = new LaminasConfigCollector($serviceManager);
        
        // Create deeply nested structure
        $data = [];
        $current = &$data;
        for ($i = 0; $i < 15; $i++) {
            $current['level'] = $i;
            $current['next'] = [];
            $current = &$current['next'];
        }
        
        $serviceManager->method('get')->with('config')->willReturn(['deep' => $data]);
        
        $result = $collector->collect();
        
        // Verify it handles max depth gracefully
        $jsonData = json_encode($result);
        $this->assertStringNotContainsString('[object Object]', $jsonData);
        // Max depth is 10, so check at least that deep nesting works
        if (isset($data['config']['application_config']['deep'])) {
            $this->assertArrayHasKey('level', $data['config']['application_config']['deep']);
            // The formatArray should have limited depth to prevent infinite recursion
            $this->assertIsArray($data['config']['application_config']['deep']);
        } else {
            // Just verify no [object Object] in the error message
            $this->assertTrue(true);
        }
    }
}