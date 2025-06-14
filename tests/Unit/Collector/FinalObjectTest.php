<?php

declare(strict_types=1);

namespace LaminasMicroscope\Tests\Unit\Collector;

use DebugBar\DebugBar;
use DebugBar\JavascriptRenderer;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Collector\LaminasConfigCollector;
use LaminasMicroscope\Collector\LaminasRequestCollector;
use LaminasMicroscope\Collector\LaminasSessionCollector;
use LaminasMicroscope\Collector\PDOCollector;
use PHPUnit\Framework\TestCase;
use ArrayObject;
use DateTime;
use stdClass;

class FinalObjectTest extends TestCase
{
    public function testNoObjectObjectInFullDebugBarOutput(): void
    {
        // Set up real-world scenario
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/users/create';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_POST['user'] = new ArrayObject(['name' => 'John', 'date' => new DateTime()]);
        $_GET['filter'] = new stdClass();

        $serviceManager = new ServiceManager();
        $serviceManager->setService('config', [
            'db' => [
                'adapter' => 'Pdo_Mysql',
                'options' => new ArrayObject(['charset' => 'utf8'])
            ],
            'complex_object' => new stdClass(),
            'closure' => function() { return 'test'; }
        ]);

        // Create full DebugBar
        $debugBar = new DebugBar();
        $debugBar->addCollector(new LaminasConfigCollector($serviceManager));
        $debugBar->addCollector(new LaminasRequestCollector($serviceManager));
        $debugBar->addCollector(new LaminasSessionCollector($serviceManager));
        $debugBar->addCollector(new PDOCollector($serviceManager, true));

        // Test JSON data (backend)
        $data = $debugBar->getData();
        $json = json_encode($data);
        
        $this->assertNotFalse($json, 'DebugBar data must be JSON serializable');
        $this->assertStringNotContainsString('[object Object]', $json, 'JSON data should not contain [object Object]');
        $this->assertStringNotContainsString('{}', $json, 'JSON data should not contain empty objects');

        // Test rendered HTML (frontend)
        $renderer = new JavascriptRenderer($debugBar);
        $head = $renderer->renderHead();
        $body = $renderer->render();
        $fullHtml = $head . $body;

        $this->assertStringNotContainsString('[object Object]', $fullHtml, 'HTML output should not contain [object Object]');
        $this->assertStringNotContainsString('<dd class="phpdebugbar-widgets-value">[object Object]</dd>', $fullHtml, 'Should not contain the specific pattern reported');

        // Verify objects are properly formatted
        $this->assertStringContainsString('"value":', $json, 'Complex objects should be wrapped in value property');

        // Clean up
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['HTTP_HOST']);
        unset($_POST['user'], $_GET['filter']);
    }

    public function testEveryCollectorIndividually(): void
    {
        $serviceManager = new ServiceManager();
        $serviceManager->setService('config', [
            'test_object' => new stdClass(),
            'test_array' => new ArrayObject(['key' => 'value'])
        ]);

        $_GET['object'] = new stdClass();
        $_POST['array_object'] = new ArrayObject(['test' => true]);

        $collectors = [
            'config' => new LaminasConfigCollector($serviceManager),
            'request' => new LaminasRequestCollector($serviceManager),
            'session' => new LaminasSessionCollector($serviceManager),
            'pdo' => new PDOCollector($serviceManager, true)
        ];

        foreach ($collectors as $name => $collector) {
            $data = $collector->collect();
            $json = json_encode($data);
            
            $this->assertNotFalse($json, "Collector '$name' data must be JSON serializable");
            $this->assertStringNotContainsString('[object Object]', $json, "Collector '$name' should not contain [object Object]");
            $this->assertStringNotContainsString('{}', $json, "Collector '$name' should not contain empty objects");
        }

        // Clean up
        unset($_GET['object'], $_POST['array_object']);
    }
}