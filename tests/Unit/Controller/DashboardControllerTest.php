<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Unit\Controller;

use LaminasMicroscope\Controller\DashboardController;
use LaminasMicroscope\Microscope\MicroscopeHandler;
use LaminasMicroscope\Registry;
use PHPUnit\Framework\TestCase;

class DashboardControllerTest extends TestCase
{
    public function testCollectorsOnlyRegistersCollectors(): void
    {
        $config = [
            'laminas_microscope' => [
                'collectors' => ['time', 'memory'],
                'components' => [
                    'debug_bar' => [
                        'enabled' => false,
                        'collectors_only' => true,
                        'collectors' => ['time', 'memory'],  // Add collectors to debug_bar config
                    ],
                ],
            ],
        ];

        $container = \TestHelper::createMockServiceManager();
        $container->set(MicroscopeHandler::class, new class {
            public function getRecentReports(int $n): array { return []; }
        });

        [$manager, $configService, $registry] = \TestHelper::createComponentManager($config, $container);

        $manager->initializeComponent('debug_bar');

        $controller = new DashboardController($manager, $configService);

        $debugBar = Registry::getDebugBar();
        $this->assertNotNull($debugBar);
        $this->assertFalse($debugBar->shouldDisplay());

        $collectors = array_keys($registry->all());
        $this->assertContains('time', $collectors);
        $this->assertContains('memory', $collectors);
    }
}
