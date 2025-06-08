<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Functional;

use PHPUnit\Framework\TestCase;
use LaminasMicroscope\Controller\DashboardController;
use Laminas\Router\RouteMatch;
use Laminas\Mvc\MvcEvent;

class DebugBarAssetsRouteTest extends TestCase
{
    private DashboardController $controller;
    private \LaminasMicroscope\Manager\ComponentManager $manager;
    private \LaminasMicroscope\Config\ConfigurationService $configService;
    private \LaminasMicroscope\Collector\CollectorRegistry $registry;

    protected function setUp(): void
    {
        [$this->manager, $this->configService, $this->registry] = \TestHelper::createComponentManager();
        $this->manager->initializeComponent('debug_bar');
        $this->controller = new DashboardController($this->manager, $this->configService);
    }

    public function testAssetsRouteReturnsDebugbarJs(): void
    {
        $request = \TestHelper::createMockRequest('GET', '/debugbar/resources/debugbar.js');
        if (method_exists($this->controller, 'setRequest')) {
            $this->controller->setRequest($request);
        }
        if (method_exists($this->controller, 'setEvent')) {
            $event = new MvcEvent();
            $event->setRouteMatch(new RouteMatch(['file' => 'debugbar.js']));
            $this->controller->setEvent($event);
        }
        $response = $this->controller->assetsAction();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
    }
}
