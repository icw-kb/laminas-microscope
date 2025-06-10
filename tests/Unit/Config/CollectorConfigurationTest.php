<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Unit\Config;

use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\DebugBar\DebugBarHandler;
use LaminasMicroscope\Microscope\MicroscopeHandler;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Collector\CollectorRegistry;
use LaminasMicroscopeTest\Unit\BaseTestCase;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class CollectorConfigurationTest extends BaseTestCase
{
    private ConfigurationService|MockObject $configService;
    private ComponentManager|MockObject $componentManager;
    private ContainerInterface|MockObject $container;
    private CollectorRegistry $collectorRegistry;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->configService = $this->createMock(ConfigurationService::class);
        $this->componentManager = $this->createMock(ComponentManager::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->collectorRegistry = new CollectorRegistry();
    }
    
    public function testDebugBarUsesComponentSpecificCollectors(): void
    {
        // Configure the mock to return specific collectors for debug_bar
        $this->configService->method('getComponentConfig')
            ->with('debug_bar')
            ->willReturn([
                'enabled' => true,
                'collectors' => ['time', 'memory', 'pdo']
            ]);
            
        $this->configService->method('isEnabled')
            ->willReturn(true);
            
        // Mock PDO collector in container
        $pdoCollector = $this->createMock(\LaminasMicroscope\DebugBar\Collectors\PDOCollector::class);
        $pdoCollector->method('getName')->willReturn('pdo');
        
        $this->container->method('has')
            ->with('LaminasMicroscope\DebugBar\Collectors\PDOCollector')
            ->willReturn(true);
        $this->container->method('get')
            ->with('LaminasMicroscope\DebugBar\Collectors\PDOCollector')
            ->willReturn($pdoCollector);
            
        $handler = new DebugBarHandler(
            $this->configService,
            $this->container,
            $this->collectorRegistry
        );
        
        $handler->initialize();
        
        // Verify that the expected collectors are registered
        $this->assertTrue($this->collectorRegistry->has('time'));
        $this->assertTrue($this->collectorRegistry->has('memory'));
        $this->assertTrue($this->collectorRegistry->has('pdo'));
        
        // Verify that collectors not in the configuration are not registered
        $this->assertFalse($this->collectorRegistry->has('exceptions'));
    }
    
    public function testDebugBarUsesDefaultCollectorsWhenNotConfigured(): void
    {
        // Configure the mock to return no collectors
        $this->configService->method('getComponentConfig')
            ->with('debug_bar')
            ->willReturn(['enabled' => true]);
            
        $this->configService->method('isEnabled')
            ->willReturn(true);
            
        $handler = new DebugBarHandler(
            $this->configService,
            $this->container,
            $this->collectorRegistry
        );
        
        $handler->initialize();
        
        // Verify that the default collectors are registered
        $this->assertTrue($this->collectorRegistry->has('time'));
        $this->assertTrue($this->collectorRegistry->has('memory'));
        $this->assertTrue($this->collectorRegistry->has('messages'));
    }
    
    public function testMicroscopeUsesComponentSpecificCollectors(): void
    {
        // Configure the mock to return specific collectors for microscope
        $this->configService->method('getComponentConfig')
            ->with('microscope')
            ->willReturn([
                'enabled' => true,
                'collectors' => ['time', 'memory', 'exceptions']
            ]);
            
        $this->componentManager->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);
            
        // Mock the storage path for microscope
        $this->configService->method('getStoragePath')
            ->willReturn('/tmp/test-microscope');
            
        $handler = new MicroscopeHandler(
            $this->componentManager,
            $this->configService,
            $this->container,
            $this->collectorRegistry
        );
        
        $handler->initialize();
        
        // Verify that the expected collectors are registered
        $this->assertTrue($this->collectorRegistry->has('time'));
        $this->assertTrue($this->collectorRegistry->has('memory'));
        $this->assertTrue($this->collectorRegistry->has('exceptions'));
    }
    
    public function testMicroscopeUsesDefaultCollectorsWhenNotConfigured(): void
    {
        // Configure the mock to return no collectors
        $this->configService->method('getComponentConfig')
            ->with('microscope')
            ->willReturn(['enabled' => true]);
            
        $this->componentManager->method('isEnabled')
            ->with('microscope')
            ->willReturn(true);
            
        // Mock the storage path for microscope
        $this->configService->method('getStoragePath')
            ->willReturn('/tmp/test-microscope');
            
        // Mock container to say PDO collector doesn't exist
        $this->container->method('has')
            ->with('LaminasMicroscope\DebugBar\Collectors\PDOCollector')
            ->willReturn(false);
            
        $handler = new MicroscopeHandler(
            $this->componentManager,
            $this->configService,
            $this->container,
            $this->collectorRegistry
        );
        
        $handler->initialize();
        
        // Verify that the default collectors are registered (except PDO which requires container)
        $this->assertTrue($this->collectorRegistry->has('time'));
        $this->assertTrue($this->collectorRegistry->has('memory'));
        $this->assertTrue($this->collectorRegistry->has('exceptions'));
        // PDO collector won't be registered because container doesn't have it
        $this->assertFalse($this->collectorRegistry->has('pdo'));
    }
    
    public function testNoGlobalCollectorsFallback(): void
    {
        // Configure the mock to ensure global collectors are never requested
        $this->configService->expects($this->never())
            ->method('get')
            ->with($this->stringContains('laminas_microscope.collectors'));
            
        $this->configService->method('getComponentConfig')
            ->willReturn(['enabled' => true, 'collectors' => ['time']]);
            
        $this->configService->method('isEnabled')
            ->willReturn(true);
            
        $handler = new DebugBarHandler(
            $this->configService,
            $this->container,
            $this->collectorRegistry
        );
        
        $handler->initialize();
        
        // Should only have the time collector
        $this->assertTrue($this->collectorRegistry->has('time'));
        $collectors = $this->collectorRegistry->all();
        $this->assertCount(1, $collectors);
    }
}