<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory\Listener;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Listener\DebugBarEventListener;
use Psr\Log\LoggerInterface;

class DebugBarEventListenerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DebugBarEventListener
    {
        $logger = null;
        if ($container->has(LoggerInterface::class)) {
            $logger = $container->get(LoggerInterface::class);
        }
        
        // Event timestamps will be passed from Module.php
        $eventTimestamps = [];
        
        return new DebugBarEventListener(
            $container, 
            $eventTimestamps, 
            $logger,
            'laminas-microscope/debugbar-assets'
        );
    }
}