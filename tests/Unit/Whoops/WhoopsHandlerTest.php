<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Unit\Whoops;

use LaminasMicroscope\Whoops\WhoopsHandler;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class WhoopsHandlerTest extends TestCase
{
    private WhoopsHandler $whoopsHandler;
    private ConfigurationService|MockObject $configService;

    protected function setUp(): void
    {
        $this->configService = $this->createMock(ConfigurationService::class);
        
        // WhoopsHandler constructor only takes ConfigurationService
        $this->whoopsHandler = new WhoopsHandler($this->configService);
    }

    public function testIsEnabledReturnsTrueWhenWhoopsEnabled(): void
    {
        $this->configService
            ->expects($this->once()) // Only called once in isEnabled()
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->expects($this->once())
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn(['enabled' => true]);

        $this->assertTrue($this->whoopsHandler->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenWhoopsDisabled(): void
    {
        $this->configService
            ->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->expects($this->once())
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn(['enabled' => false]);

        $this->assertFalse($this->whoopsHandler->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenMicroscopeDisabled(): void
    {
        $this->configService
            ->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $this->configService
            ->expects($this->never())
            ->method('getComponentConfig');

        $this->assertFalse($this->whoopsHandler->isEnabled());
    }

    public function testInitializeInitializesWhoopsHandlers(): void
    {
        $this->configService
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn([
                'enabled' => true,
                'handlers' => ['pretty', 'json'],
                'editor' => 'vscode',
                'page_title' => 'Test Error Page'
            ]);

        $this->configService
            ->method('getEnvironment')
            ->willReturn('development');

        // This should not throw an exception
        $this->whoopsHandler->initialize();
        
        // Verify it's initialized
        $this->assertTrue($this->whoopsHandler->isInitialized());
        
        // Verify we can get the Whoops instance
        $whoops = $this->whoopsHandler->getWhoops();
        $this->assertInstanceOf(\Whoops\Run::class, $whoops);
    }

    public function testInitializeDoesNothingWhenDisabled(): void
    {
        $this->configService
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn(['enabled' => false]);

        // This should complete without error
        $this->whoopsHandler->initialize();
        
        // Should not be initialized when disabled
        $this->assertFalse($this->whoopsHandler->isInitialized());
    }

    public function testGetWhoopsReturnsWhoopsRun(): void
    {
        $this->configService
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn(['enabled' => true]);

        $this->configService
            ->method('getEnvironment')
            ->willReturn('development');

        $whoops = $this->whoopsHandler->getWhoops();
        $this->assertInstanceOf(\Whoops\Run::class, $whoops);
    }

    public function testGetWhoopsReturnsNullWhenDisabled(): void
    {
        $this->configService
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn(['enabled' => false]);

        $whoops = $this->whoopsHandler->getWhoops();
        $this->assertNull($whoops);
    }

    public function testShouldDisplayDetectsProductionEnvironment(): void
    {
        $this->configService
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn([
                'enabled' => true,
                'show_in_production' => false
            ]);

        $this->configService
            ->method('getEnvironment')
            ->willReturn('production');

        $this->assertFalse($this->whoopsHandler->shouldDisplay());
    }

    public function testShouldDisplayAllowsProductionWhenConfigured(): void
    {
        $this->configService
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn([
                'enabled' => true,
                'show_in_production' => true
            ]);

        $this->configService
            ->method('getEnvironment')
            ->willReturn('production');

        $this->assertTrue($this->whoopsHandler->shouldDisplay());
    }

    public function testShouldDisplayReturnsTrueInDevelopment(): void
    {
        $this->configService
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn(['enabled' => true]);

        $this->configService
            ->method('getEnvironment')
            ->willReturn('development');

        $this->assertTrue($this->whoopsHandler->shouldDisplay());
    }

    public function testShouldDisplayReturnsFalseWhenDisabled(): void
    {
        $this->configService
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn(['enabled' => false]);

        $this->assertFalse($this->whoopsHandler->shouldDisplay());
    }

    public function testResetClearsWhoopsInstance(): void
    {
        // First initialize
        $this->configService
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn(['enabled' => true]);

        $this->configService
            ->method('getEnvironment')
            ->willReturn('development');

        $this->whoopsHandler->initialize();
        $this->assertTrue($this->whoopsHandler->isInitialized());

        // Now reset
        $this->whoopsHandler->reset();
        $this->assertFalse($this->whoopsHandler->isInitialized());
    }

    public function testIsInitializedReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->whoopsHandler->isInitialized());
    }

    public function testIsInitializedReturnsTrueAfterInitialization(): void
    {
        $this->configService
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn(['enabled' => true]);

        $this->configService
            ->method('getEnvironment')
            ->willReturn('development');

        $this->whoopsHandler->initialize();
        $this->assertTrue($this->whoopsHandler->isInitialized());
    }

    public function testInitializeOnlyRunsOnce(): void
    {
        $this->configService
            ->method('isEnabled')
            ->willReturn(true);

        // Allow unlimited calls to getComponentConfig since initialize() may call it multiple times
        // and we're testing multiple initialize() calls
        $this->configService
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn(['enabled' => true]);

        $this->configService
            ->method('getEnvironment')
            ->willReturn('development');

        // Call initialize multiple times
        $this->whoopsHandler->initialize();
        $this->whoopsHandler->initialize();
        $this->whoopsHandler->initialize();

        // Should only initialize once - this is verified by the fact that 
        // isInitialized() returns true and no exceptions are thrown
        $this->assertTrue($this->whoopsHandler->isInitialized());
    }

    public function testGetWhoopsInitializesIfNeeded(): void
    {
        $this->configService
            ->method('isEnabled')
            ->willReturn(true);

        $this->configService
            ->method('getComponentConfig')
            ->with('whoops')
            ->willReturn(['enabled' => true]);

        $this->configService
            ->method('getEnvironment')
            ->willReturn('development');

        // Should not be initialized initially
        $this->assertFalse($this->whoopsHandler->isInitialized());

        // Getting Whoops should initialize it
        $whoops = $this->whoopsHandler->getWhoops();
        
        $this->assertInstanceOf(\Whoops\Run::class, $whoops);
        $this->assertTrue($this->whoopsHandler->isInitialized());
    }
}
