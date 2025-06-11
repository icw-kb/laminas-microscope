<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Integration;

use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\DebugBar\DebugBarHandler;
use LaminasMicroscope\DebugBar\CollectorFactory;
use LaminasMicroscope\Collector\CollectorRegistry;
use LaminasMicroscope\Container\MockContainer;
use LaminasMicroscope\DebugBar\Collectors\LaminasConfigCollector;
use LaminasMicroscope\DebugBar\Collectors\LaminasRequestCollector;
use LaminasMicroscope\DebugBar\Collectors\PDOCollector;
use PHPUnit\Framework\TestCase;
use Laminas\ServiceManager\ServiceManager;

class ActualDisplayTest extends TestCase
{
    private ConfigurationService $configService;
    private CollectorRegistry $collectorRegistry;
    private MockContainer $container;
    private CollectorFactory $collectorFactory;
    
    protected function setUp(): void
    {
        // Use the EXACT configuration from the actual project
        $config = [
            'laminas_microscope' => [
                'enabled' => true,
                'environment' => 'development',
                'components' => [
                    'debug_bar' => [
                        'enabled' => true,
                        'collectors' => [
                            'time',
                            'memory', 
                            'pdo',
                            'request',
                            'config',  // ← This should show in DebugBar
                        ],
                    ],
                    'microscope' => [
                        'enabled' => true,
                        'collectors' => [
                            'time',
                            'memory',
                            'pdo', 
                            'exceptions',  // ← This should NOT show in DebugBar
                        ],
                    ],
                ],
            ],
        ];
        
        $this->configService = new ConfigurationService($config);
        $this->collectorRegistry = new CollectorRegistry();
        $this->container = new MockContainer();
        
        // Mock the collector services
        $serviceManager = new ServiceManager();
        $this->container->set(LaminasConfigCollector::class, new LaminasConfigCollector($serviceManager));
        $this->container->set(LaminasRequestCollector::class, new LaminasRequestCollector($serviceManager));
        $this->container->set(PDOCollector::class, new PDOCollector($serviceManager, true));
        
        $this->collectorFactory = new CollectorFactory($this->container);
    }
    
    public function testDebugBarWidgetOutput(): void
    {
        $handler = new DebugBarHandler($this->configService, $this->container, $this->collectorRegistry, $this->collectorFactory);
        $handler->initialize();
        
        // Test what the actual DebugBar widget shows to users
        $debugBar = $handler->getDebugBar();
        $this->assertNotNull($debugBar, 'DebugBar should be initialized');
        
        // Get the actual HTML output that users see
        $renderer = $handler->getRenderer();
        $this->assertNotNull($renderer, 'DebugBar renderer should be available');
        
        $htmlOutput = $renderer->render();
        $this->assertNotEmpty($htmlOutput, 'DebugBar should render HTML output');
        
        echo "\n=== DebugBar Widget Analysis ===\n";
        echo "HTML Output Length: " . strlen($htmlOutput) . " characters\n";
        
        // Check if the HTML contains DebugBar JavaScript
        $this->assertStringContainsString('PhpDebugBar', $htmlOutput, 'HTML should contain DebugBar JavaScript');
        
        // Analyze what widgets are actually being added
        $tabWidgets = [];
        $indicatorWidgets = [];
        
        // Look for addTab calls (these create clickable tabs)
        if (preg_match_all('/addTab\("([^"]*)"/', $htmlOutput, $matches)) {
            $tabWidgets = $matches[1];
        }
        
        // Look for addIndicator calls (these create small toolbar icons)
        if (preg_match_all('/addIndicator\("([^"]*)"/', $htmlOutput, $matches)) {
            $indicatorWidgets = $matches[1];
        }
        
        echo "Tab widgets (clickable): " . implode(', ', $tabWidgets) . "\n";
        echo "Indicator widgets (toolbar icons): " . implode(', ', $indicatorWidgets) . "\n";
        
        // According to the configuration, user should see these as tabs:
        // time, memory, pdo, request, config
        // But some might be indicators vs tabs based on their widget definitions
        
        // Expected tabs based on configuration: timeline, pdo, request, config
        // Expected indicators: time, memory
        $expectedTabs = ['timeline', 'pdo', 'request', 'config'];
        $expectedIndicators = ['time', 'memory'];
        
        echo "\nExpected tabs: " . implode(', ', $expectedTabs) . "\n";
        echo "Expected indicators: " . implode(', ', $expectedIndicators) . "\n";
        
        // Verify tabs are present
        foreach ($expectedTabs as $expectedTab) {
            $this->assertContains($expectedTab, $tabWidgets, "'{$expectedTab}' should be a clickable tab");
        }
        
        // Verify indicators are present  
        foreach ($expectedIndicators as $expectedIndicator) {
            $this->assertContains($expectedIndicator, $indicatorWidgets, "'{$expectedIndicator}' should be a toolbar indicator");
        }
        
        // User says they only see "timeline" - but they should see 4 tabs total
        $this->assertCount(4, $tabWidgets, 'DebugBar should show 4 clickable tabs: timeline, pdo, request, config');
        
        // If user only sees timeline, the others might be hidden by CSS or JavaScript errors
    }
    
    public function testMicroscopeDashboardDisplay(): void
    {
        // Test what the Microscope dashboard actually shows
        $microscopeConfig = $this->configService->getComponentConfig('microscope');
        $configuredCollectors = $microscopeConfig['collectors'] ?? [];
        
        echo "\n=== Microscope Dashboard Analysis ===\n";
        echo "Configured collectors: " . implode(', ', $configuredCollectors) . "\n";
        
        // Simulate what the view template (index.phtml) would show
        // This is what users see in the Microscope dashboard sidebar
        
        $expectedMicroscopeCollectors = ['time', 'memory', 'pdo', 'exceptions'];
        $this->assertEquals($expectedMicroscopeCollectors, $configuredCollectors, 
            'Microscope should show exactly: time, memory, pdo, exceptions');
        
        // User reports seeing: "Time, memory, PDO, exceptions" - this is CORRECT!
        foreach ($expectedMicroscopeCollectors as $collector) {
            $this->assertContains($collector, $configuredCollectors, 
                "Microscope dashboard should show '{$collector}' collector");
            echo "✓ '{$collector}' collector configured for Microscope dashboard\n";
        }
        
        // Verify 'config' is NOT in Microscope (user says it shouldn't be)
        $this->assertNotContains('config', $configuredCollectors, 
            'Config collector should NOT be in Microscope dashboard');
        echo "✓ 'config' collector correctly NOT in Microscope dashboard\n";
    }
    
    public function testActualConfigurationIsolation(): void
    {
        echo "\n=== Configuration Isolation Test ===\n";
        
        $debugBarConfig = $this->configService->getComponentConfig('debug_bar');
        $microscopeConfig = $this->configService->getComponentConfig('microscope');
        
        $debugBarCollectors = $debugBarConfig['collectors'] ?? [];
        $microscopeCollectors = $microscopeConfig['collectors'] ?? [];
        
        echo "DebugBar collectors: " . implode(', ', $debugBarCollectors) . "\n";
        echo "Microscope collectors: " . implode(', ', $microscopeCollectors) . "\n";
        
        // According to user's report:
        // - DebugBar should show: time, memory, pdo, request, config (but only shows timeline)
        // - Microscope should show: time, memory, pdo, exceptions (and does)
        
        $expectedDebugBar = ['time', 'memory', 'pdo', 'request', 'config'];
        $expectedMicroscope = ['time', 'memory', 'pdo', 'exceptions'];
        
        $this->assertEquals($expectedDebugBar, $debugBarCollectors, 
            'DebugBar configuration should match expected');
        $this->assertEquals($expectedMicroscope, $microscopeCollectors, 
            'Microscope configuration should match expected');
        
        // Key differences that should exist:
        $this->assertContains('config', $debugBarCollectors, 'DebugBar should have config');
        $this->assertNotContains('config', $microscopeCollectors, 'Microscope should NOT have config');
        
        $this->assertContains('exceptions', $microscopeCollectors, 'Microscope should have exceptions');
        $this->assertNotContains('exceptions', $debugBarCollectors, 'DebugBar should NOT have exceptions');
        
        $this->assertContains('request', $debugBarCollectors, 'DebugBar should have request');
        $this->assertNotContains('request', $microscopeCollectors, 'Microscope should NOT have request');
    }
    
    public function testDebugBarJavaScriptRendering(): void
    {
        echo "\n=== DebugBar JavaScript Rendering Test ===\n";
        
        $handler = new DebugBarHandler($this->configService, $this->container, $this->collectorRegistry, $this->collectorFactory);
        $handler->initialize();
        
        $renderer = $handler->getRenderer();
        $this->assertNotNull($renderer, 'Renderer should be available');
        
        // Test the head assets (CSS)
        $headContent = $renderer->renderHead();
        $this->assertNotEmpty($headContent, 'Head content should not be empty');
        echo "Head content contains CSS: " . (strpos($headContent, '<style') !== false ? 'YES' : 'NO') . "\n";
        
        // Test the body content (JavaScript and HTML)
        $bodyContent = $renderer->render();
        $this->assertNotEmpty($bodyContent, 'Body content should not be empty');
        echo "Body content contains JavaScript: " . (strpos($bodyContent, '<script') !== false ? 'YES' : 'NO') . "\n";
        
        // Check for DebugBar initialization
        $this->assertStringContainsString('PhpDebugBar', $bodyContent, 'Should contain PhpDebugBar JavaScript');
        
        // Check if collectors are being initialized in JavaScript
        $collectors = ['time', 'memory', 'pdo', 'request', 'config'];
        foreach ($collectors as $collector) {
            if (strpos($bodyContent, $collector) !== false) {
                echo "✓ '{$collector}' found in JavaScript output\n";
            } else {
                echo "✗ '{$collector}' NOT found in JavaScript output\n";
            }
        }
    }
}