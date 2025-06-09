<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory\Listener;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Listener\WhoopsEventListener;
use Psr\Log\LoggerInterface;

class WhoopsEventListenerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): WhoopsEventListener
    {
        $logger = null;
        if ($container->has(LoggerInterface::class)) {
            $logger = $container->get(LoggerInterface::class);
        }
        
        return new WhoopsEventListener($container, $logger);
    }
}