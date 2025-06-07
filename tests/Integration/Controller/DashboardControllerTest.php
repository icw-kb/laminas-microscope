<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Integration\Controller;

use LaminasMicroscope\Controller\DashboardController;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Collector\CollectorRegistry;
use PHPUnit\Framework\TestCase;

class DashboardControllerTest extends TestCase
{
    private DashboardController $controller;
    private ComponentManager $componentManager;
    private ConfigurationService $configService;
    private CollectorRegistry $registry;

    protected function setUp(): void
    {
        $config = \TestHelper::createMockConfig();
        $this->configService = new ConfigurationService($config);
        $this->registry = new CollectorRegistry();
        $this->componentManager = $this->createMock(ComponentManager::class);
        $this->componentManager->method('isEnabled')->willReturn(true);
        $this->controller = new DashboardController($this->componentManager, $this->configService, $this->registry);
    }

    public function testIndexActionProvidesCollectors(): void
    {
        $this->registry->register(new class {
            public function getName(): string { return 'sample'; }
        });

        $result = $this->controller->indexAction();
        $this->assertInstanceOf(\Laminas\View\Model\ViewModel::class, $result);
        $vars = $result->getVariables();
        $this->assertArrayHasKey('collectors', $vars);
        $this->assertContains('sample', $vars['collectors']);
    }
}

