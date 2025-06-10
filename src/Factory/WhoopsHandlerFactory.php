<?php

declare(strict_types=1);

namespace LaminasMicroscope\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Whoops\WhoopsHandler;
use Psr\Container\ContainerInterface;

class WhoopsHandlerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): WhoopsHandler
    {
        $config = $container->get(ConfigurationService::class);

        return new WhoopsHandler($config);
    }
}
