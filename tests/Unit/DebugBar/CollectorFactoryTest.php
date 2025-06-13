<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Unit\DebugBar;

use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\TimeDataCollector;
use LaminasMicroscope\DebugBar\CollectorFactory;
use LaminasMicroscope\Collector\PDOCollector;
use LaminasMicroscope\Exception\ConfigurationException;
use LaminasMicroscopeTest\Unit\BaseTestCase;
use Psr\Container\ContainerInterface;

class CollectorFactoryTest extends BaseTestCase
{
    private CollectorFactory $factory;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory = $this->createCollectorFactory($this->container);
    }

    public function testCreateDirectCollector(): void
    {
        $collector = $this->factory->create('time');
        
        $this->assertInstanceOf(TimeDataCollector::class, $collector);
        $this->assertInstanceOf(DataCollectorInterface::class, $collector);
    }

    public function testCreateMemoryCollector(): void
    {
        $collector = $this->factory->create('memory');
        
        $this->assertInstanceOf(MemoryCollector::class, $collector);
        $this->assertInstanceOf(DataCollectorInterface::class, $collector);
    }

    public function testCreateServiceCollector(): void
    {
        $mockPdoCollector = $this->createMock(PDOCollector::class);
        
        $this->container->expects($this->once())
            ->method('has')
            ->with(PDOCollector::class)
            ->willReturn(true);
            
        $this->container->expects($this->once())
            ->method('get')
            ->with(PDOCollector::class)
            ->willReturn($mockPdoCollector);
        
        $collector = $this->factory->create('pdo');
        
        $this->assertSame($mockPdoCollector, $collector);
    }

    public function testCreateUnknownCollectorReturnsNull(): void
    {
        $collector = $this->factory->create('unknown');
        
        $this->assertNull($collector);
    }

    public function testCreateServiceCollectorNotInContainerReturnsNull(): void
    {
        $this->container->expects($this->once())
            ->method('has')
            ->with(PDOCollector::class)
            ->willReturn(false);
            
        $collector = $this->factory->create('pdo');
        
        $this->assertNull($collector);
    }

    public function testGetCollectorConfig(): void
    {
        $config = $this->factory->getCollectorConfig('time');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('class', $config);
        $this->assertArrayHasKey('factory', $config);
        $this->assertEquals('direct', $config['factory']);
    }

    public function testGetCollectorConfigForUnknownReturnsNull(): void
    {
        $config = $this->factory->getCollectorConfig('unknown');
        
        $this->assertNull($config);
    }

    public function testGetAvailableCollectors(): void
    {
        $collectors = $this->factory->getAvailableCollectors();
        
        $this->assertIsArray($collectors);
        $this->assertContains('time', $collectors);
        $this->assertContains('memory', $collectors);
        $this->assertContains('messages', $collectors);
        $this->assertContains('phpinfo', $collectors);
        $this->assertContains('php', $collectors);
        $this->assertContains('config', $collectors);
        $this->assertContains('pdo', $collectors);
        $this->assertContains('request', $collectors);
    }

    public function testServiceCollectorNotImplementingInterfaceThrowsException(): void
    {
        $invalidCollector = new \stdClass();
        
        $this->container->expects($this->once())
            ->method('has')
            ->with(PDOCollector::class)
            ->willReturn(true);
            
        $this->container->expects($this->once())
            ->method('get')
            ->with(PDOCollector::class)
            ->willReturn($invalidCollector);
        
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Service '" . PDOCollector::class . "' does not implement DataCollectorInterface");
        
        $this->factory->create('pdo');
    }

    public function testPhpInfoCollectorConfig(): void
    {
        $config = $this->factory->getCollectorConfig('phpinfo');
        
        $this->assertEquals(\DebugBar\DataCollector\PhpInfoCollector::class, $config['class']);
        $this->assertEquals('direct', $config['factory']);
    }

    public function testPhpCollectorConfig(): void
    {
        $config = $this->factory->getCollectorConfig('php');
        
        $this->assertEquals(\DebugBar\DataCollector\PhpInfoCollector::class, $config['class']);
        $this->assertEquals('direct', $config['factory']);
    }
}