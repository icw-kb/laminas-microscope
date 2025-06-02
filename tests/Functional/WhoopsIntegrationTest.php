<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Functional;

use PHPUnit\Framework\TestCase;
use LaminasMicroscope\Whoops\WhoopsHandler;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;

class WhoopsIntegrationTest extends TestCase
{
    private WhoopsHandler $whoops;
    private ComponentManager $componentManager;
    private ConfigurationService $configService;
    private string $tempDir;

    protected function setUp(): void
    {
        // Create configuration using TestHelper
        $config = \TestHelper::createMockConfig([
            'laminas_microscope' => [
                'enabled' => true,
                'environment' => 'testing',
                'components' => [
                    'whoops' => [
                        'enabled' => true,
                        'editor' => 'vscode',
                        'editor_url' => null,
                        'show_in_production' => false,
                        'blacklist' => [],
                        'hide_superglobals' => false,
                        'handlers' => ['pretty', 'json'],
                        'page_title' => 'Whoops! There was an error.',
                        'json_api' => true,
                    ],
                ],
                'storage' => [
                    'path' => sys_get_temp_dir() . '/laminas_microscope_test',
                ],
            ],
        ]);
        
        // Create dependencies in correct order
        $this->configService = new ConfigurationService($config);
        $this->componentManager = new ComponentManager($this->configService);
        
        // Create WhoopsHandler with correct constructor signature (only ConfigurationService)
        $this->whoops = new WhoopsHandler($this->configService);
        
        $this->tempDir = \TestHelper::createTempDir();
    }

    protected function tearDown(): void
    {
        \TestHelper::cleanupTempDir($this->tempDir);
        
        // Reset whoops to clean state
        if (method_exists($this->whoops, 'reset')) {
            $this->whoops->reset();
        }
    }

    public function testWhoopsCanBeCreated(): void
    {
        $this->assertInstanceOf(WhoopsHandler::class, $this->whoops);
    }

    public function testWhoopsIsEnabledInTestEnvironment(): void
    {
        $this->assertTrue($this->whoops->isEnabled());
    }

    public function testWhoopsInitializesCorrectly(): void
    {
        if (!$this->whoops->isEnabled()) {
            $this->markTestSkipped('Whoops is not enabled');
        }

        // This should not throw an exception
        $this->whoops->initialize();
        
        $this->assertTrue($this->whoops->isInitialized());
        
        $whoopsRun = $this->whoops->getWhoops();
        $this->assertInstanceOf(\Whoops\Run::class, $whoopsRun);
        
        $handlers = $whoopsRun->getHandlers();
        $this->assertGreaterThan(0, count($handlers));
    }

    public function testWhooopsShouldDisplayInTestEnvironment(): void
    {
        // In testing environment, should display
        $this->assertTrue($this->whoops->shouldDisplay());
    }

    public function testWhoopsDetectsProductionEnvironment(): void
    {
        // Create a whoops handler with production environment
        $productionConfig = \TestHelper::createMockConfig([
            'laminas_microscope' => [
                'enabled' => true,
                'environment' => 'production',
                'components' => [
                    'whoops' => [
                        'enabled' => true,
                        'show_in_production' => false,
                    ],
                ],
            ],
        ]);
        
        $productionConfigService = new ConfigurationService($productionConfig);
        $productionWhoops = new WhoopsHandler($productionConfigService);
        
        // Should not display in production when show_in_production is false
        $this->assertFalse($productionWhoops->shouldDisplay());
    }

    public function testWhoopsShowsInProductionWhenConfigured(): void
    {
        // Create a whoops handler with production environment but show_in_production=true
        $productionConfig = \TestHelper::createMockConfig([
            'laminas_microscope' => [
                'enabled' => true,
                'environment' => 'production',
                'components' => [
                    'whoops' => [
                        'enabled' => true,
                        'show_in_production' => true,
                    ],
                ],
            ],
        ]);
        
        $productionConfigService = new ConfigurationService($productionConfig);
        $productionWhoops = new WhoopsHandler($productionConfigService);
        
        // Should display in production when show_in_production is true
        $this->assertTrue($productionWhoops->shouldDisplay());
    }

    public function testWhoopsGetWhoopsCreatesRunInstance(): void
    {
        $whoopsRun = $this->whoops->getWhoops();
        
        $this->assertInstanceOf(\Whoops\Run::class, $whoopsRun);
        
        // Verify it's the same instance on subsequent calls
        $whoopsRun2 = $this->whoops->getWhoops();
        $this->assertSame($whoopsRun, $whoopsRun2);
    }

    public function testWhoopsInitializeSetsUpHandlers(): void
    {
        if (!$this->whoops->isEnabled()) {
            $this->markTestSkipped('Whoops is not enabled');
        }

        // Initialize handlers
        $this->whoops->initialize();
        
        $this->assertTrue($this->whoops->isInitialized());
        
        $whoopsRun = $this->whoops->getWhoops();
        $handlers = $whoopsRun->getHandlers();
        
        // Should have at least one handler (pretty and json from config)
        $this->assertGreaterThan(0, count($handlers));
        
        // Verify handler types
        $handlerTypes = [];
        foreach ($handlers as $handler) {
            $handlerTypes[] = get_class($handler);
        }
        
        // Should contain PrettyPageHandler and JsonResponseHandler based on config
        $this->assertContains('Whoops\\Handler\\PrettyPageHandler', $handlerTypes);
        $this->assertContains('Whoops\\Handler\\JsonResponseHandler', $handlerTypes);
    }

    public function testWhoopsMethodsExist(): void
    {
        // Test that all expected methods exist
        $this->assertTrue(method_exists($this->whoops, 'isEnabled'));
        $this->assertTrue(method_exists($this->whoops, 'initialize'));
        $this->assertTrue(method_exists($this->whoops, 'getWhoops'));
        $this->assertTrue(method_exists($this->whoops, 'shouldDisplay'));
        $this->assertTrue(method_exists($this->whoops, 'reset'));
        $this->assertTrue(method_exists($this->whoops, 'isInitialized'));
    }

    public function testWhoopsDisabledBehavior(): void
    {
        // Create disabled whoops handler
        $disabledConfig = \TestHelper::createMockConfig([
            'laminas_microscope' => [
                'enabled' => true,
                'environment' => 'testing',
                'components' => [
                    'whoops' => [
                        'enabled' => false,
                    ],
                ],
            ],
        ]);
        
        $disabledConfigService = new ConfigurationService($disabledConfig);
        $disabledWhoops = new WhoopsHandler($disabledConfigService);
        
        $this->assertFalse($disabledWhoops->isEnabled());
        $this->assertFalse($disabledWhoops->shouldDisplay());
        
        // Should not initialize when disabled
        $disabledWhoops->initialize();
        $this->assertFalse($disabledWhoops->isInitialized());
        
        // getWhoops should return null when disabled
        $whoopsRun = $disabledWhoops->getWhoops();
        $this->assertNull($whoopsRun);
    }

    public function testWhoopsHandlerConfiguration(): void
    {
        // Test different handler configurations
        $handlerConfigs = [
            ['pretty'],
            ['json'],
            ['plain'],
            ['pretty', 'json'],
            ['pretty', 'json', 'plain'],
        ];
        
        foreach ($handlerConfigs as $handlers) {
            $handlerConfig = \TestHelper::createMockConfig([
                'laminas_microscope' => [
                    'enabled' => true,
                    'environment' => 'testing',
                    'components' => [
                        'whoops' => [
                            'enabled' => true,
                            'handlers' => $handlers,
                        ],
                    ],
                ],
            ]);
            
            $handlerConfigService = new ConfigurationService($handlerConfig);
            $handlerWhoops = new WhoopsHandler($handlerConfigService);
            
            $handlerWhoops->initialize();
            $whoopsRun = $handlerWhoops->getWhoops();
            
            $this->assertInstanceOf(\Whoops\Run::class, $whoopsRun);
            
            $runHandlers = $whoopsRun->getHandlers();
            $this->assertEquals(count($handlers), count($runHandlers), 
                'Handler count should match configuration for: ' . implode(', ', $handlers));
            
            // Clean up
            $handlerWhoops->reset();
        }
    }

    public function testWhoopsResetFunctionality(): void
    {
        if (!$this->whoops->isEnabled()) {
            $this->markTestSkipped('Whoops is not enabled');
        }

        // Initialize whoops
        $this->whoops->initialize();
        $this->assertTrue($this->whoops->isInitialized());
        
        $whoopsRun = $this->whoops->getWhoops();
        $this->assertInstanceOf(\Whoops\Run::class, $whoopsRun);
        
        // Reset whoops
        $this->whoops->reset();
        $this->assertFalse($this->whoops->isInitialized());
        
        // After reset, getWhoops should reinitialize
        $newWhoopsRun = $this->whoops->getWhoops();
        $this->assertInstanceOf(\Whoops\Run::class, $newWhoopsRun);
        $this->assertTrue($this->whoops->isInitialized());
    }

    public function testWhoopsConfigurationIsApplied(): void
    {
        if (!$this->whoops->isEnabled()) {
            $this->markTestSkipped('Whoops is not enabled');
        }

        // Initialize with specific configuration
        $this->whoops->initialize();
        
        $whoopsRun = $this->whoops->getWhoops();
        $this->assertNotNull($whoopsRun);
        $this->assertInstanceOf(\Whoops\Run::class, $whoopsRun);
        
        $handlers = $whoopsRun->getHandlers();
        $this->assertGreaterThan(0, count($handlers));
        
        // Verify pretty page handler exists and is configured
        $prettyHandler = null;
        foreach ($handlers as $handler) {
            if ($handler instanceof \Whoops\Handler\PrettyPageHandler) {
                $prettyHandler = $handler;
                break;
            }
        }
        
        $this->assertNotNull($prettyHandler, 'PrettyPageHandler should be present');
    }

    public function testWhoopsEnvironmentSpecificBehavior(): void
    {
        $environments = ['development', 'testing', 'staging', 'production'];
        
        foreach ($environments as $environment) {
            $envConfig = \TestHelper::createMockConfig([
                'laminas_microscope' => [
                    'enabled' => true,
                    'environment' => $environment,
                    'components' => [
                        'whoops' => [
                            'enabled' => true,
                            'show_in_production' => false,
                        ],
                    ],
                ],
            ]);
            
            $envConfigService = new ConfigurationService($envConfig);
            $envWhoops = new WhoopsHandler($envConfigService);
            
            if ($environment === 'production') {
                $this->assertFalse($envWhoops->shouldDisplay(), "Whoops should not display in production by default");
            } else {
                $this->assertTrue($envWhoops->shouldDisplay(), "Whoops should display in {$environment} environment");
            }
        }
    }

    public function testWhoopsWithCustomConfiguration(): void
    {
        // Create whoops with custom configuration
        $customConfig = \TestHelper::createMockConfig([
            'laminas_microscope' => [
                'enabled' => true,
                'environment' => 'testing',
                'components' => [
                    'whoops' => [
                        'enabled' => true,
                        'editor' => 'phpstorm',
                        'page_title' => 'Custom Error Page',
                        'handlers' => ['pretty'],
                        'json_api' => false,
                    ],
                ],
            ],
        ]);
        
        $customConfigService = new ConfigurationService($customConfig);
        $customWhoops = new WhoopsHandler($customConfigService);
        
        $this->assertTrue($customWhoops->isEnabled());
        $this->assertTrue($customWhoops->shouldDisplay());
        
        $customWhoops->initialize();
        $whoopsRun = $customWhoops->getWhoops();
        $this->assertInstanceOf(\Whoops\Run::class, $whoopsRun);
        
        $handlers = $whoopsRun->getHandlers();
        $this->assertEquals(1, count($handlers), 'Should have only one handler (pretty)');
        
        $customWhoops->reset();
    }
}
