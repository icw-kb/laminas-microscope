<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Whoops\WhoopsHandler;
use LaminasMicroscope\Config\ConfigurationService;

class WhoopsHandlerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): WhoopsHandler
    {
        $config = $container->get(ConfigurationService::class);
        
        return new WhoopsHandler($config);
    }
}