<?php

declare(strict_types=1);

namespace LaminasMicroscope\Tests\Unit\Collector;

use DateTime;
use DebugBar\DebugBar;
use DebugBar\JavascriptRenderer;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\RouteMatch;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Collector\LaminasConfigCollector;
use LaminasMicroscope\Collector\LaminasRequestCollector;
use LaminasMicroscope\Collector\LaminasSessionCollector;
use LaminasMicroscope\Collector\EnhancedPDOCollector;
use PHPUnit\Framework\TestCase;
use ArrayObject;
use stdClass;

class RealWorldObjectTest extends TestCase
{
    private ServiceManager $serviceManager;

    protected function setUp(): void
    {
        $this->serviceManager = new ServiceManager();
        
        // Simulate real application conditions
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/users/create';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SESSION = [];
        
        // Add complex real-world objects to globals
        $_POST['user'] = new ArrayObject([
            'name' => 'John Doe',
            'created' => new DateTime(),
            'metadata' => new stdClass()
        ]);
        
        $_GET['filter'] = new ArrayObject(['active' => true]);
        
        // Mock a complex config with real objects
        $this->serviceManager->setService('config', [
            'db' => [
                'adapter' => 'Pdo_Mysql',
                'driver_options' => [
                    'closure' => function() { return 'test'; }
                ]
            ],
            'session_config' => new ArrayObject(['name' => 'PHPSESSID']),
            'cache_config' => new stdClass(),
        ]);
    }

    public function testRealWorldDebugBarOutput(): void
    {
        // Create DebugBar with all collectors like in real application
        $debugBar = new DebugBar();
        
        $configCollector = new LaminasConfigCollector($this->serviceManager);
        $requestCollector = new LaminasRequestCollector($this->serviceManager);
        $sessionCollector = new LaminasSessionCollector($this->serviceManager);
        
        $debugBar->addCollector($configCollector);
        $debugBar->addCollector($requestCollector);
        $debugBar->addCollector($sessionCollector);
        
        // Get the data as DebugBar would
        $data = $debugBar->getData();
        
        // Convert to JSON exactly as the JavaScript renderer does
        $jsonData = json_encode($data);
        $this->assertNotFalse($jsonData, 'DebugBar data must be JSON serializable');
        
        // Key test: should not contain [object Object] anywhere
        $this->assertStringNotContainsString('[object Object]', $jsonData, 'Should not contain [object Object] in real-world data');
        
        // Should not contain empty objects that become [object Object] in JS
        $this->assertStringNotContainsString('{}', $jsonData, 'Should not contain empty objects');
        
        // Verify complex objects are handled
        $this->assertStringContainsString('user', $jsonData, 'Should contain POST data');
        $this->assertStringContainsString('filter', $jsonData, 'Should contain GET data');
    }

    public function testJavaScriptRendererOutput(): void
    {
        // Test the actual JavaScript renderer that generates HTML
        $debugBar = new DebugBar();
        $debugBar->addCollector(new LaminasRequestCollector($this->serviceManager));
        
        $renderer = new JavascriptRenderer($debugBar);
        
        // Get the actual JavaScript that would be rendered
        $jsContent = $renderer->renderHead();
        $jsContent .= $renderer->render();
        
        // This JavaScript should not contain [object Object] when executed
        $this->assertStringNotContainsString('[object Object]', $jsContent, 'JavaScript output should not contain [object Object]');
    }

    public function testEnhancedPDOCollectorWithRealQueryData(): void
    {
        $collector = new EnhancedPDOCollector($this->serviceManager, true);
        
        // Add queries with complex parameter objects (common in real apps)
        $collector->addQuery([
            'sql' => 'SELECT * FROM users WHERE created_at = ? AND metadata = ?',
            'params' => [
                new DateTime('2023-01-01'),
                new ArrayObject(['role' => 'admin']),
                'nested' => new stdClass()
            ],
            'duration' => 150,
            'memory' => 2048,
            'is_success' => true,
            'error_code' => null,
            'error_message' => null,
        ]);
        
        $data = $collector->collect();
        $json = json_encode($data);
        
        $this->assertNotFalse($json, 'Enhanced PDO data should be JSON serializable');
        $this->assertStringNotContainsString('[object Object]', $json, 'PDO collector should not have [object Object]');
        $this->assertStringNotContainsString('{}', $json, 'PDO collector should not have empty objects');
    }

    public function testComplexRouteMatchObjects(): void
    {
        // Simulate route match with complex objects (common in real Laminas apps)
        $routeMatch = new RouteMatch([
            'controller' => 'Application\Controller\UserController',
            'action' => 'create',
            'id' => '123',
            'metadata' => new ArrayObject(['source' => 'api']),
            'context' => new stdClass()
        ]);
        
        $application = $this->createMock(Application::class);
        $mvcEvent = $this->createMock(MvcEvent::class);
        $mvcEvent->method('getRouteMatch')->willReturn($routeMatch);
        $application->method('getMvcEvent')->willReturn($mvcEvent);
        
        $this->serviceManager->setService('Application', $application);
        
        $collector = new LaminasRequestCollector($this->serviceManager);
        $data = $collector->collect();
        
        $json = json_encode($data);
        $this->assertNotFalse($json, 'Route data should be JSON serializable');
        $this->assertStringNotContainsString('[object Object]', $json, 'Route data should not have [object Object]');
    }

    public function testClosureAndResourceHandling(): void
    {
        // Test non-serializable objects that exist in real applications
        $config = [
            'callback' => function() { return 'test'; },
            'file_handle' => fopen('php://memory', 'r'),
            'service' => new class {
                public function __toString() {
                    return 'Anonymous Service';
                }
            }
        ];
        
        $this->serviceManager->setService('config', $config);
        
        $collector = new LaminasConfigCollector($this->serviceManager);
        $data = $collector->collect();
        
        $json = json_encode($data);
        $this->assertNotFalse($json, 'Config with closures/resources should be JSON serializable');
        $this->assertStringNotContainsString('[object Object]', $json, 'Should handle closures and resources safely');
        
        // Clean up
        fclose($config['file_handle']);
    }

    public function testLargeDataSets(): void
    {
        // Test with large datasets that might cause issues
        $largeArray = [];
        for ($i = 0; $i < 100; $i++) {
            $largeArray["item_$i"] = new stdClass();
            $largeArray["item_$i"]->data = "value_$i";
            $largeArray["item_$i"]->nested = new ArrayObject(['index' => $i]);
        }
        
        $this->serviceManager->setService('config', [
            'large_dataset' => $largeArray
        ]);
        
        $collector = new LaminasConfigCollector($this->serviceManager);
        $data = $collector->collect();
        
        $json = json_encode($data);
        $this->assertNotFalse($json, 'Large datasets should be JSON serializable');
        $this->assertStringNotContainsString('[object Object]', $json, 'Large datasets should not have [object Object]');
    }

    public function testActualDebugBarHtmlOutput(): void
    {
        // Test what would actually be rendered in the browser
        $debugBar = new DebugBar();
        $debugBar->addCollector(new LaminasRequestCollector($this->serviceManager));
        $debugBar->addCollector(new LaminasConfigCollector($this->serviceManager));
        
        $renderer = new JavascriptRenderer($debugBar);
        
        // Get the HTML that would be injected into the page
        $head = $renderer->renderHead();
        $body = $renderer->render();
        
        // This is the actual HTML that gets displayed
        $fullHtml = $head . $body;
        
        // The critical test: the rendered HTML should not contain [object Object]
        $this->assertStringNotContainsString('[object Object]', $fullHtml, 'Final HTML output should not contain [object Object]');
        
        // Should also not contain the problematic pattern from the user's report
        $this->assertStringNotContainsString('<dd class="phpdebugbar-widgets-value">[object Object]</dd>', $fullHtml, 'Should not contain the specific HTML pattern reported');
    }

    protected function tearDown(): void
    {
        // Clean up globals
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);
        unset($_SERVER['HTTP_HOST']);
        unset($_POST['user']);
        unset($_GET['filter']);
        $_SESSION = [];
    }
}