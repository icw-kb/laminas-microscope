<?php

namespace icwkb\Microscope

use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\Mvc\MvcEvent;

/**
 * @author Abdul Malik Ikhsan <samsonasik@gmail.com>
 */
class Module implements ConfigProviderInterface
{
    public function onBootstrap(MvcEvent $mvcEvent)
    {
    }


    public function getConfig(): array
    {
        return include __DIR__.'/../config/module.config.php';
    }
}
