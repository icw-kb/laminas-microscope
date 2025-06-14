<?php

declare(strict_types=1);

namespace LaminasMicroscope\Tests\Unit\Collector;

use DebugBar\DebugBar;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Collector\LaminasConfigCollector;
use LaminasMicroscope\Collector\LaminasRequestCollector;
use LaminasMicroscope\Collector\LaminasSessionCollector;
use LaminasMicroscope\Collector\PDOCollector;
use PHPUnit\Framework\TestCase;
use stdClass;

class ObjectFormattingTest extends TestCase
{
    private ServiceManager $serviceManager;

    protected function setUp(): void
    {
        $this->serviceManager = new ServiceManager();
        $this->serviceManager->setService('config', [
            'test' => 'value',
            'nested' => [
                'object' => new stdClass(),
                'array' => ['key' => new stdClass()]
            ]
        ]);
    }

    public function testLaminasConfigCollectorFormatsObjects(): void
    {
        $collector = new LaminasConfigCollector($this->serviceManager);
        $data = $collector->collect();
        
        // Convert to JSON to simulate what DebugBar does
        $json = json_encode($data);
        $this->assertNotFalse($json, 'Data should be JSON serializable');
        
        // Check that no objects remain in the JSON
        $this->assertStringNotContainsString('{}', $json, 'Should not contain empty objects from failed serialization');
        
        // Verify objects are properly formatted
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        
        // Check that any object references are converted to strings
        $this->assertObjectsAreFormatted($decoded);
    }

    public function testLaminasRequestCollectorFormatsObjects(): void
    {
        // Mock request data with objects
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $_GET['test'] = new stdClass();
        
        $collector = new LaminasRequestCollector($this->serviceManager);
        $data = $collector->collect();
        
        // Convert to JSON to simulate what DebugBar does
        $json = json_encode($data);
        $this->assertNotFalse($json, 'Data should be JSON serializable');
        
        // Check that no objects remain in the JSON
        $this->assertStringNotContainsString('{}', $json, 'Should not contain empty objects');
        
        // Verify objects are properly formatted
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertObjectsAreFormatted($decoded);
    }

    public function testLaminasSessionCollectorFormatsObjects(): void
    {
        $collector = new LaminasSessionCollector($this->serviceManager);
        $data = $collector->collect();
        
        // Convert to JSON to simulate what DebugBar does
        $json = json_encode($data);
        $this->assertNotFalse($json, 'Data should be JSON serializable');
        
        // Check that no objects remain in the JSON
        $this->assertStringNotContainsString('{}', $json, 'Should not contain empty objects');
        
        // Verify objects are properly formatted
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertObjectsAreFormatted($decoded);
    }

    public function testPDOCollectorFormatsObjects(): void
    {
        $collector = new PDOCollector($this->serviceManager, true); // Skip DB setup
        
        // Add a query with object data
        $objectData = new stdClass();
        $objectData->test = 'value';
        
        $collector->addQuery([
            'sql' => 'SELECT * FROM test',
            'params' => ['object' => $objectData],
            'duration' => 100,
            'memory' => 1024,
            'is_success' => true,
            'error_code' => null,
            'error_message' => null,
        ]);
        
        $data = $collector->collect();
        
        // Convert to JSON to simulate what DebugBar does
        $json = json_encode($data);
        $this->assertNotFalse($json, 'Data should be JSON serializable');
        
        // Check that no objects remain in the JSON
        $this->assertStringNotContainsString('{}', $json, 'Should not contain empty objects');
        
        // Verify objects are properly formatted
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertObjectsAreFormatted($decoded);
    }

    public function testCircularReferenceHandling(): void
    {
        $obj1 = new stdClass();
        $obj2 = new stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1; // Circular reference
        
        // Create new service manager for this test
        $sm = new ServiceManager();
        $sm->setService('config', [
            'circular' => $obj1
        ]);
        
        $collector = new LaminasConfigCollector($sm);
        $data = $collector->collect();
        
        // Should not cause memory exhaustion
        $json = json_encode($data);
        $this->assertNotFalse($json, 'Should handle circular references without memory issues');
        
        // DebugBar's DataFormatter should handle circular references properly
        // It uses object IDs like {#1226} to represent circular references
        $this->assertMatchesRegularExpression('/\{#\d+\}/', $json, 'Should contain DebugBar circular reference markers');
    }

    public function testDeepNestingHandling(): void
    {
        // Create deeply nested structure
        $deep = new stdClass();
        $current = $deep;
        for ($i = 0; $i < 15; $i++) {
            $current->next = new stdClass();
            $current = $current->next;
        }
        
        // Create new service manager for this test
        $sm = new ServiceManager();
        $sm->setService('config', [
            'deep' => $deep
        ]);
        
        $collector = new LaminasConfigCollector($sm);
        $data = $collector->collect();
        
        // Should not cause memory exhaustion
        $json = json_encode($data);
        $this->assertNotFalse($json, 'Should handle deep nesting without memory issues');
        
        // For deep nesting, DebugBar should either format properly or we should see our depth limit
        $this->assertTrue(
            strpos($json, 'MAX DEPTH REACHED') !== false || strpos($json, '{#') !== false,
            'Should handle deep nesting with either depth limits or DebugBar formatting'
        );
    }

    public function testDebugBarIntegration(): void
    {
        // Test actual DebugBar integration
        $debugBar = new DebugBar();
        
        $configCollector = new LaminasConfigCollector($this->serviceManager);
        $requestCollector = new LaminasRequestCollector($this->serviceManager);
        
        $debugBar->addCollector($configCollector);
        $debugBar->addCollector($requestCollector);
        
        // This should not throw any errors
        $data = $debugBar->getData();
        $this->assertIsArray($data);
        
        // Convert to JSON as DebugBar does
        $json = json_encode($data);
        $this->assertNotFalse($json, 'DebugBar data should be JSON serializable');
        
        // Should not contain problematic objects
        $this->assertStringNotContainsString('{}', $json, 'Should not contain empty objects in DebugBar output');
        
        // Check specifically for [object Object] pattern that would appear in HTML
        $this->assertStringNotContainsString('[object Object]', $json, 'Should not contain [object Object] in JSON data');
    }

    public function testSpecificObjectObjectIssue(): void
    {
        // Create a scenario that would cause [object Object] in HTML
        $problemObject = new stdClass();
        $problemObject->nested = new stdClass();
        $problemObject->nested->value = 'test';
        
        $_GET['problem'] = $problemObject;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        
        $collector = new LaminasRequestCollector($this->serviceManager);
        $data = $collector->collect();
        
        // Convert to JSON to simulate what DebugBar does
        $json = json_encode($data);
        $this->assertNotFalse($json, 'Data should be JSON serializable');
        
        // The key test: should not contain [object Object]
        $this->assertStringNotContainsString('[object Object]', $json, 'Should not contain [object Object] pattern');
        
        // Should also not contain empty objects that would become [object Object] in JS
        $this->assertStringNotContainsString('{}', $json, 'Should not contain empty objects');
        
        // Verify the object was actually processed
        $this->assertStringContainsString('problem', $json, 'Should contain the problem parameter');
    }

    public function testVariableListWidgetCompatibility(): void
    {
        // Test that complex objects are wrapped in value property for VariableListWidget
        $complexObject = new stdClass();
        $complexObject->nested = new stdClass();
        $complexObject->nested->value = 'test';
        
        $sm = new ServiceManager();
        $sm->setService('config', [
            'complex' => $complexObject,
            'array' => ['key1', 'key2', 'key3', 'key4', 'key5', 'key6'], // Large array
        ]);
        
        $collector = new LaminasConfigCollector($sm);
        $data = $collector->collect();
        
        $json = json_encode($data);
        
        // Should not contain [object Object]
        $this->assertStringNotContainsString('[object Object]', $json);
        
        // Should contain value properties for complex objects
        $this->assertStringContainsString('"value":', $json, 'Complex objects should be wrapped in value property');
        
        // Should not contain empty objects
        $this->assertStringNotContainsString('{}', $json);
    }

    /**
     * Recursively check that all objects in the data are properly formatted as strings
     */
    private function assertObjectsAreFormatted($data): void
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                $this->assertObjectsAreFormatted($value);
            }
        } else {
            // All values should be scalars (strings, numbers, booleans, null)
            $this->assertTrue(
                is_scalar($value) || is_null($value),
                'All values should be scalars after formatting, got: ' . gettype($value)
            );
        }
    }

    protected function tearDown(): void
    {
        // Clean up global state
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['test']);
    }
}