<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory\Listener;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Listener\MicroscopeEventListener;
use Psr\Log\LoggerInterface;

class MicroscopeEventListenerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): MicroscopeEventListener
    {
        $logger = null;
        if ($container->has(LoggerInterface::class)) {
            $logger = $container->get(LoggerInterface::class);
        }
        
        return new MicroscopeEventListener($container, $logger);
    }
}