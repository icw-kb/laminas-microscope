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

class CollectorIntegrationTest extends TestCase
{
    private ConfigurationService $configService;
    private CollectorRegistry $collectorRegistry;
    private MockContainer $container;
    private CollectorFactory $collectorFactory;
    
    protected function setUp(): void
    {
        // Use the actual configuration from the project
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
                            'config',
                        ],
                    ],
                    'microscope' => [
                        'enabled' => true,
                        'collectors' => [
                            'time',
                            'memory',
                            'pdo', 
                            'exceptions',
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
    
    public function testDebugBarShowsConfiguredCollectors(): void
    {
        $handler = new DebugBarHandler($this->configService, $this->container, $this->collectorRegistry, $this->collectorFactory);
        $handler->initialize();
        
        $debugBar = $handler->getDebugBar();
        $this->assertNotNull($debugBar, 'DebugBar should be initialized');
        
        $collectors = $debugBar->getCollectors();
        $collectorNames = array_keys($collectors);
        
        // DebugBar should show exactly what's configured: time, memory, pdo, request, config
        $expectedCollectors = ['time', 'memory', 'pdo', 'request', 'config'];
        
        // Verify the exact collectors match configuration
        
        $this->assertEquals(
            $expectedCollectors, 
            $collectorNames, 
            "DebugBar collectors don't match configuration"
        );
        
        // DebugBar should NOT show collectors that aren't configured
        $this->assertNotContains(
            'exceptions', 
            $collectorNames, 
            "DebugBar should NOT show 'exceptions' collector as it's not configured in debug_bar.collectors"
        );
        
        // Should not have extra collectors beyond what's configured
        $extraCollectors = array_diff($collectorNames, $expectedCollectors);
        $this->assertEmpty(
            $extraCollectors,
            'DebugBar should not have extra collectors beyond configuration. Found: ' . implode(', ', $extraCollectors)
        );
    }
    
    public function testMicroscopeDashboardShowsConfiguredCollectors(): void
    {
        // Test what the microscope dashboard view would show
        $microscopeConfig = $this->configService->getComponentConfig('microscope');
        $configuredCollectors = $microscopeConfig['collectors'] ?? [];
        
        $expectedCollectors = ['time', 'memory', 'pdo', 'exceptions'];
        
        $this->assertEquals(
            $expectedCollectors,
            $configuredCollectors,
            'Microscope dashboard should be configured to show: ' . implode(', ', $expectedCollectors)
        );
        
        // Should NOT include config collector
        $this->assertNotContains(
            'config',
            $configuredCollectors,
            "Microscope should NOT show 'config' collector as it's not in microscope.collectors configuration"
        );
    }
    
    public function testCollectorConfigurationIsolation(): void
    {
        $debugBarConfig = $this->configService->getComponentConfig('debug_bar');
        $microscopeConfig = $this->configService->getComponentConfig('microscope');
        
        $debugBarCollectors = $debugBarConfig['collectors'] ?? [];
        $microscopeCollectors = $microscopeConfig['collectors'] ?? [];
        
        // Verify the configurations are different
        $this->assertNotEquals(
            $debugBarCollectors,
            $microscopeCollectors,
            'DebugBar and Microscope should have different collector configurations'
        );
        
        // DebugBar has 'config', Microscope doesn't
        $this->assertContains('config', $debugBarCollectors, 'DebugBar should have config collector');
        $this->assertNotContains('config', $microscopeCollectors, 'Microscope should not have config collector');
        
        // Microscope has 'exceptions', DebugBar doesn't  
        $this->assertContains('exceptions', $microscopeCollectors, 'Microscope should have exceptions collector');
        $this->assertNotContains('exceptions', $debugBarCollectors, 'DebugBar should not have exceptions collector');
    }
    
    public function testActualCollectorInstances(): void
    {
        $handler = new DebugBarHandler($this->configService, $this->container, $this->collectorRegistry, $this->collectorFactory);
        $handler->initialize();
        
        $debugBar = $handler->getDebugBar();
        $collectors = $debugBar->getCollectors();
        
        // Test specific collector types
        $this->assertInstanceOf(
            'DebugBar\DataCollector\TimeDataCollector',
            $collectors['time'] ?? null,
            'Time collector should be TimeDataCollector'
        );
        
        $this->assertInstanceOf(
            'DebugBar\DataCollector\MemoryCollector', 
            $collectors['memory'] ?? null,
            'Memory collector should be MemoryCollector'
        );
        
        $this->assertInstanceOf(
            'LaminasMicroscope\DebugBar\Collectors\LaminasConfigCollector',
            $collectors['config'] ?? null,
            'Config collector should be LaminasConfigCollector'
        );
        
        $this->assertInstanceOf(
            'LaminasMicroscope\DebugBar\Collectors\LaminasRequestCollector',
            $collectors['request'] ?? null,
            'Request collector should be LaminasRequestCollector'
        );
    }
}