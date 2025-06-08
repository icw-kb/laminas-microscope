<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Unit\Config;

use LaminasMicroscope\Config\ConfigurationService;
use PHPUnit\Framework\TestCase;

class ConfigurationServiceTest extends TestCase
{
    private ConfigurationService $configService;
    private array $testConfig;

    protected function setUp(): void
    {
        $this->testConfig = \TestHelper::createMockConfig();
        $this->configService = new ConfigurationService($this->testConfig);
    }

    public function testConstructorSetsConfig(): void
    {
        $config = ['test' => 'value'];
        $service = new ConfigurationService($config);
        
        $this->assertEquals($config, $service->toArray());
    }

    public function testGetReturnsConfigValue(): void
    {
        $value = $this->configService->get('laminas_microscope.enabled');
        $this->assertTrue($value);
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $value = $this->configService->get('nonexistent.key', 'default');
        $this->assertEquals('default', $value);
    }

    public function testGetReturnsNullForMissingKeyWithoutDefault(): void
    {
        $value = $this->configService->get('nonexistent.key');
        $this->assertNull($value);
    }

    public function testGetNestedConfigValue(): void
    {
        $value = $this->configService->get('laminas_microscope.components.whoops.enabled');
        $this->assertTrue($value);
    }

    public function testGetWithArrayValue(): void
    {
        $value = $this->configService->get('laminas_microscope.collectors');
        $this->assertIsArray($value);
        $this->assertContains('time', $value);
        $this->assertContains('memory', $value);
        $this->assertContains('pdo', $value);
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->assertTrue($this->configService->has('laminas_microscope.enabled'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->configService->has('nonexistent.key'));
    }

    public function testHasWorksWithNestedKeys(): void
    {
        $this->assertTrue($this->configService->has('laminas_microscope.components.whoops.enabled'));
        $this->assertFalse($this->configService->has('laminas_microscope.components.nonexistent.enabled'));
    }

    public function testSetCreatesNewConfigValue(): void
    {
        $this->configService->set('new.config.value', 'test');
        $this->assertEquals('test', $this->configService->get('new.config.value'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $this->configService->set('laminas_microscope.enabled', false);
        $this->assertFalse($this->configService->get('laminas_microscope.enabled'));
    }

    public function testToArrayReturnsFullConfig(): void
    {
        $config = $this->configService->toArray();
        $this->assertArrayHasKey('laminas_microscope', $config);
    }

    public function testGetEnvironmentReturnsCorrectValue(): void
    {
        $environment = $this->configService->getEnvironment();
        $this->assertEquals('testing', $environment);
    }

    public function testGetEnvironmentReturnsDefaultWhenNotSet(): void
    {
        $service = new ConfigurationService([]);
        $environment = $service->getEnvironment();
        $this->assertEquals('development', $environment);
    }

    public function testIsEnabledReturnsCorrectValue(): void
    {
        $this->assertTrue($this->configService->isEnabled());
    }

    public function testIsEnabledReturnsTrueByDefault(): void
    {
        $service = new ConfigurationService([]);
        $this->assertTrue($service->isEnabled());
    }

    public function testGetStoragePathReturnsCorrectPath(): void
    {
        $path = $this->configService->getStoragePath();
        $expected = sys_get_temp_dir() . '/laminas-microscope-test';
        $this->assertEquals($expected, $path);
    }

    public function testGetStoragePathReturnsDefaultWhenNotSet(): void
    {
        $service = new ConfigurationService([]);
        $path = $service->getStoragePath();
        $this->assertEquals('data/laminas-microscope', $path);
    }

    public function testGetComponentConfigReturnsComponentSettings(): void
    {
        $whoopsConfig = $this->configService->getComponentConfig('whoops');
        $this->assertArrayHasKey('enabled', $whoopsConfig);
        $this->assertTrue($whoopsConfig['enabled']);
        $this->assertArrayHasKey('show_in_production', $whoopsConfig);
        $this->assertFalse($whoopsConfig['show_in_production']);
    }

    public function testGetComponentConfigReturnsEmptyArrayForMissingComponent(): void
    {
        $config = $this->configService->getComponentConfig('nonexistent');
        $this->assertIsArray($config);
        $this->assertEmpty($config);
    }

    public function testSetConfigUpdatesConfiguration(): void
    {
        $newConfig = ['laminas_microscope' => ['enabled' => false]];
        $this->configService->setConfig($newConfig);
        
        $this->assertFalse($this->configService->get('laminas_microscope.enabled'));
    }

    public function testMergeConfigCombinesConfigurations(): void
    {
        $additionalConfig = [
            'laminas_microscope' => [
                'components' => [
                    'new_component' => ['enabled' => true]
                ]
            ]
        ];
        
        $this->configService->mergeConfig($additionalConfig);
        
        $this->assertTrue($this->configService->has('laminas_microscope.components.new_component.enabled'));
        $this->assertTrue($this->configService->get('laminas_microscope.components.new_component.enabled'));
    }

    public function testGetRetentionDaysReturnsCorrectValue(): void
    {
        $days = $this->configService->getRetentionDays();
        $this->assertEquals(7, $days);
    }

    public function testGetRetentionDaysReturnsDefaultWhenNotSet(): void
    {
        $service = new ConfigurationService([]);
        $days = $service->getRetentionDays();
        $this->assertEquals(30, $days);
    }

    public function testIsDebugModeReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->configService->isDebugMode());
    }

    public function testGetMaxFileSizeReturnsDefaultValue(): void
    {
        $service = new ConfigurationService([]);
        $size = $service->getMaxFileSize();
        $this->assertEquals(50 * 1024 * 1024, $size); // 50MB
    }

    public function testGetAllowedExtensionsReturnsDefaultArray(): void
    {
        $service = new ConfigurationService([]);
        $extensions = $service->getAllowedExtensions();
        $this->assertIsArray($extensions);
        $this->assertContains('json', $extensions);
        $this->assertContains('log', $extensions);
        $this->assertContains('txt', $extensions);
    }
}
