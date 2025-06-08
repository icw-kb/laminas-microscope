<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Unit\Manager;

use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\DebugBar\DebugBarHandler;
use PHPUnit\Framework\TestCase;

class ComponentManagerTest extends TestCase
{
    private ComponentManager $manager;
    private ConfigurationService $configService;
    private \LaminasMicroscope\Collector\CollectorRegistry $registry;

    protected function setUp(): void
    {
        $config = \TestHelper::createMockConfig([
            'laminas_microscope' => [
                'enabled' => true,
                'collectors' => ['time', 'memory'],
                'components' => [
                    'debug_bar' => [
                        'enabled' => true,
                        'type' => 'profiler',
                    ],
                    'whoops' => [
                        'enabled' => false,
                        'type' => 'error_handler',
                    ],
                    'analysis' => [
                        'enabled' => true,
                        'type' => 'analyzer',
                        'dependencies' => ['debug_bar'],
                    ],
                    'microscope' => [
                        'enabled' => true,
                        'type' => 'profiler',
                    ],
                ],
            ],
        ]);
        
        [$this->manager, $this->configService, $this->registry] = \TestHelper::createComponentManager($config);
    }

    public function testGetComponentConfigReturnsConfiguration(): void
    {
        $config = $this->manager->getComponentConfig('debug_bar');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertTrue($config['enabled']);
        $this->assertEquals('profiler', $config['type']);
    }

    public function testIsEnabledReturnsTrueForEnabledComponent(): void
    {
        $this->assertTrue($this->manager->isEnabled('debug_bar'));
        $this->assertTrue($this->manager->isEnabled('microscope'));
    }

    public function testIsEnabledReturnsFalseForDisabledComponent(): void
    {
        $this->assertFalse($this->manager->isEnabled('whoops'));
    }

    public function testIsComponentEnabledReturnsTrueForEnabledComponent(): void
    {
        $this->assertTrue($this->manager->isComponentEnabled('debug_bar'));
        $this->assertTrue($this->manager->isComponentEnabled('microscope'));
    }

    public function testIsComponentEnabledReturnsFalseForDisabledComponent(): void
    {
        $this->assertFalse($this->manager->isComponentEnabled('whoops'));
    }

    public function testIsComponentEnabledReturnsFalseForUnknownComponent(): void
    {
        $this->assertFalse($this->manager->isComponentEnabled('unknown_component'));
    }

    public function testGetRegisteredComponentsReturnsAllComponents(): void
    {
        $components = $this->manager->getRegisteredComponents();
        
        $this->assertIsArray($components);
        $this->assertContains('debug_bar', $components);
        $this->assertContains('whoops', $components);
        $this->assertContains('analysis', $components);
        $this->assertContains('microscope', $components);
    }

    public function testGetEnabledComponentsReturnsOnlyEnabledComponents(): void
    {
        $components = $this->manager->getEnabledComponents();
        
        $this->assertIsArray($components);
        $this->assertContains('debug_bar', $components);
        $this->assertContains('analysis', $components);
        $this->assertContains('microscope', $components);
        $this->assertNotContains('whoops', $components);
    }

    public function testGetComponentReturnsNullForUnknownComponent(): void
    {
        $component = $this->manager->getComponent('unknown_component');
        $this->assertNull($component);
    }

    public function testGetComponentReturnsComponentInstance(): void
    {
        $component = $this->manager->getComponent('debug_bar');
        $this->assertInstanceOf(DebugBarHandler::class, $component);
    }

    public function testRegisterComponentAddsNewComponent(): void
    {
        $this->manager->registerComponent('test_component', \stdClass::class);
        
        $components = $this->manager->getRegisteredComponents();
        $this->assertContains('test_component', $components);
    }

    public function testInitializeComponentReturnsNullForDisabledComponent(): void
    {
        $component = $this->manager->initializeComponent('whoops');
        $this->assertNull($component);
    }

    public function testInitializeComponentReturnsInstanceForEnabledComponent(): void
    {
        $component = $this->manager->initializeComponent('debug_bar');
        $this->assertInstanceOf(DebugBarHandler::class, $component);
        $this->assertTrue($this->manager->isComponentInitialized('debug_bar'));
    }

    public function testInitializeAllComponentsReturnsEnabledComponents(): void
    {
        $components = $this->manager->initializeAllComponents();
        
        $this->assertIsArray($components);
        $this->assertArrayHasKey('debug_bar', $components);
        $this->assertArrayHasKey('analysis', $components);
        $this->assertArrayNotHasKey('whoops', $components);
    }

    public function testIsComponentInitializedReturnsFalseInitially(): void
    {
        $this->assertFalse($this->manager->isComponentInitialized('debug_bar'));
    }

    public function testIsComponentInitializedReturnsTrueAfterInitialization(): void
    {
        $this->manager->initializeComponent('debug_bar');
        $this->assertTrue($this->manager->isComponentInitialized('debug_bar'));
    }

    public function testResetComponentClearsInitializedState(): void
    {
        $this->manager->initializeComponent('debug_bar');
        $this->assertTrue($this->manager->isComponentInitialized('debug_bar'));
        
        $this->manager->resetComponent('debug_bar');
        $this->assertFalse($this->manager->isComponentInitialized('debug_bar'));
    }

    public function testResetAllComponentsClearsAllInitializedStates(): void
    {
        $this->manager->initializeAllComponents();
        $this->assertTrue($this->manager->isComponentInitialized('debug_bar'));
        
        $this->manager->resetAllComponents();
        $this->assertFalse($this->manager->isComponentInitialized('debug_bar'));
        $this->assertFalse($this->manager->isComponentInitialized('analysis'));
    }

    public function testGetComponentStatusReturnsStatusInformation(): void
    {
        $status = $this->manager->getComponentStatus();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('debug_bar', $status);
        $this->assertArrayHasKey('microscope', $status);
        
        $debugBarStatus = $status['debug_bar'];
        $this->assertTrue($debugBarStatus['registered']);
        $this->assertTrue($debugBarStatus['enabled']);
        $this->assertFalse($debugBarStatus['initialized']);
        $this->assertIsArray($debugBarStatus['config']);
    }

    public function testGetComponentsByTypeReturnsComponentsOfSpecificType(): void
    {
        $profilers = $this->manager->getComponentsByType('profiler');
        
        $this->assertIsArray($profilers);
        $this->assertArrayHasKey('debug_bar', $profilers);
        $this->assertArrayHasKey('microscope', $profilers);
        $this->assertArrayNotHasKey('whoops', $profilers);
    }

    public function testHasEnabledComponentsReturnsTrueWhenComponentsEnabled(): void
    {
        $this->assertTrue($this->manager->hasEnabledComponents());
    }

    public function testHasEnabledComponentsReturnsFalseWhenNoComponentsEnabled(): void
    {
        // Disable all components
        $config = \TestHelper::createMockConfig([
            'laminas_microscope' => [
                'enabled' => true,
                'components' => [
                    'debug_bar' => ['enabled' => false],
                    'whoops' => ['enabled' => false],
                    'analysis' => ['enabled' => false],
                    'microscope' => ['enabled' => false],
                ],
            ],
        ]);
        
        [$manager, $configService, $registry] = \TestHelper::createComponentManager($config);
        
        $this->assertFalse($manager->hasEnabledComponents());
    }

    public function testGetComponentDependenciesReturnsEmptyArrayWhenNoDependencies(): void
    {
        $dependencies = $this->manager->getComponentDependencies('debug_bar');
        $this->assertIsArray($dependencies);
        $this->assertEmpty($dependencies);
    }

    public function testGetComponentDependenciesReturnsDependencies(): void
    {
        $dependencies = $this->manager->getComponentDependencies('analysis');
        $this->assertIsArray($dependencies);
        $this->assertContains('debug_bar', $dependencies);
    }

    public function testValidateDependenciesReturnsEmptyArrayWhenAllSatisfied(): void
    {
        $missing = $this->manager->validateDependencies('analysis');
        $this->assertIsArray($missing);
        $this->assertEmpty($missing);
    }

    public function testValidateDependenciesReturnsMissingDependencies(): void
    {
        // Add component with unsatisfied dependency
        $this->configService->set('laminas_microscope.components.test.dependencies', ['missing_component']);
        
        $missing = $this->manager->validateDependencies('test');
        $this->assertIsArray($missing);
        $this->assertContains('missing_component', $missing);
    }

    public function testGetInitializationOrderReturnsCorrectOrder(): void
    {
        $order = $this->manager->getInitializationOrder();
        
        $this->assertIsArray($order);
        $this->assertContains('debug_bar', $order);
        $this->assertContains('analysis', $order);
        
        // debug_bar should come before analysis due to dependency
        $debugBarIndex = array_search('debug_bar', $order);
        $analysisIndex = array_search('analysis', $order);
        $this->assertLessThan($analysisIndex, $debugBarIndex);
    }

    public function testEnableComponentEnablesComponent(): void
    {
        $this->assertFalse($this->manager->isComponentEnabled('whoops'));
        
        $result = $this->manager->enableComponent('whoops');
        $this->assertTrue($result);
        $this->assertTrue($this->manager->isComponentEnabled('whoops'));
    }

    public function testEnableComponentReturnsFalseForUnknownComponent(): void
    {
        $result = $this->manager->enableComponent('unknown_component');
        $this->assertFalse($result);
    }

    public function testDisableComponentDisablesComponent(): void
    {
        $this->assertTrue($this->manager->isComponentEnabled('debug_bar'));
        
        $result = $this->manager->disableComponent('debug_bar');
        $this->assertTrue($result);
        $this->assertFalse($this->manager->isComponentEnabled('debug_bar'));
    }

    public function testDisableComponentReturnsFalseForUnknownComponent(): void
    {
        $result = $this->manager->disableComponent('unknown_component');
        $this->assertFalse($result);
    }

    public function testGetComponentMetricsReturnsMetrics(): void
    {
        $metrics = $this->manager->getComponentMetrics();
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_registered', $metrics);
        $this->assertArrayHasKey('total_enabled', $metrics);
        $this->assertArrayHasKey('total_initialized', $metrics);
        $this->assertArrayHasKey('memory_usage', $metrics);
        
        $this->assertIsInt($metrics['total_registered']);
        $this->assertIsInt($metrics['total_enabled']);
        $this->assertIsInt($metrics['total_initialized']);
        $this->assertIsInt($metrics['memory_usage']);
    }

    public function testExportConfigurationReturnsAllComponentConfigs(): void
    {
        $config = $this->manager->exportConfiguration();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('debug_bar', $config);
        $this->assertArrayHasKey('whoops', $config);
        $this->assertArrayHasKey('analysis', $config);
        $this->assertArrayHasKey('microscope', $config);
    }

    public function testImportConfigurationUpdatesComponentConfigs(): void
    {
        $newConfig = [
            'debug_bar' => [
                'enabled' => false,
                'new_setting' => 'test_value',
            ],
        ];
        
        $this->manager->importConfiguration($newConfig);
        
        $debugBarConfig = $this->manager->getComponentConfig('debug_bar');
        $this->assertFalse($debugBarConfig['enabled']);
        $this->assertEquals('test_value', $debugBarConfig['new_setting']);
    }
}
