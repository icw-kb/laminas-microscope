<?php

declare(strict_types=1);

use LaminasMicroscope\Listener\WhoopsEventListener;
use LaminasMicroscope\Listener\MicroscopeEventListener;
use LaminasMicroscope\Listener\DebugBarEventListener;
use Laminas\ServiceManager\ServiceManager;

/**
 * Service manager configuration for the new event listener classes
 * 
 * Add this to the 'service_manager' => 'factories' array in module.config.php
 */
return [
    'service_manager' => [
        'factories' => [
            // Event Listeners
            WhoopsEventListener::class => function (ServiceManager $container) {
                $logger = null;
                if ($container->has(\Psr\Log\LoggerInterface::class)) {
                    $logger = $container->get(\Psr\Log\LoggerInterface::class);
                }
                return new WhoopsEventListener($container, $logger);
            },
            
            MicroscopeEventListener::class => function (ServiceManager $container) {
                $logger = null;
                if ($container->has(\Psr\Log\LoggerInterface::class)) {
                    $logger = $container->get(\Psr\Log\LoggerInterface::class);
                }
                return new MicroscopeEventListener($container, $logger);
            },
            
            DebugBarEventListener::class => function (ServiceManager $container) {
                $logger = null;
                if ($container->has(\Psr\Log\LoggerInterface::class)) {
                    $logger = $container->get(\Psr\Log\LoggerInterface::class);
                }
                
                // Event timestamps would be passed from Module.php
                $eventTimestamps = [];
                
                return new DebugBarEventListener(
                    $container, 
                    $eventTimestamps, 
                    $logger,
                    'laminas-microscope/debugbar-assets'
                );
            },
        ],
    ],
];