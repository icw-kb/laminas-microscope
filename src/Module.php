<?php

declare(strict_types=1);

namespace LaminasMicroscope;

use Laminas\ModuleManager\Feature\AutoloaderProviderInterface;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\ModuleManager\Feature\InitProviderInterface;
use Laminas\ModuleManager\Feature\BootstrapListenerInterface;
use Laminas\ModuleManager\ModuleManagerInterface;
use Laminas\EventManager\EventInterface;
use Laminas\Mvc\MvcEvent;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\DebugBar\DebugBarHandler; // Added use statement
use LaminasMicroscope\Microscope\MicroscopeHandler; // Added use statement
use Closure; // Added use statement for Closure::fromCallable (though we'll use anonymous functions)

class Module implements
    AutoloaderProviderInterface,
    ConfigProviderInterface,
    InitProviderInterface,
    BootstrapListenerInterface
{
    public function init(ModuleManagerInterface $manager): void
    {
        // Early initialization for configuration loading
    }

    public function onBootstrap(EventInterface $e): void
    {
        $serviceManager = $e->getApplication()->getServiceManager();
        $componentManager = $serviceManager->get(ComponentManager::class);

        // Initialize all enabled components early in the bootstrap
        // This ensures Whoops\Run::register() is called if Whoops is enabled
        $componentManager->initializeAllComponents();

        // Only attach listeners for enabled components (excluding the onError listener)
        $this->attachEventListeners($e, $componentManager);
    }

    public function getConfig(): array
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getAutoloaderConfig(): array
    {
        return [
            'Laminas\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__,
                ],
            ],
        ];
    }

    private function attachEventListeners(EventInterface $e, ComponentManager $componentManager): void
    {
        $eventManager = $e->getApplication()->getEventManager();
        $serviceManager = $e->getApplication()->getServiceManager(); // Get ServiceManager here

        // Debug bar
        if ($componentManager->isEnabled('debug_bar')) {
            // Listener to inject the debug bar at the end of the request
            $eventManager->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $event) use ($serviceManager) {
                $handler = $serviceManager->get(DebugBarHandler::class);
                $handler->injectDebugBar($event);
            }, -1000); // High priority to run late

            // Listener to log response headers and result type at the RENDER event
            $eventManager->attach(MvcEvent::EVENT_RENDER, function (MvcEvent $event) use ($serviceManager) {
                 // We don't need the handler instance here, just the static method
                 DebugBarHandler::logResponseHeadersAndResultAtRender($event);
            }, 1); // Low priority to run early in RENDER

            // --- NEW DEBUG LISTENER ---
            // Listener to log MvcEvent result type at the DISPATCH event
            $eventManager->attach(MvcEvent::EVENT_DISPATCH, function (MvcEvent $event) use ($serviceManager) {
                 // We don't need the handler instance here, just the static method
                 DebugBarHandler::logMvcEventResultAtDispatch($event);
            }, -1); // High priority to run late in DISPATCH (after controller returns result)
            // --- END NEW DEBUG LISTENER ---
        }

        // Microscope profiling
        if ($componentManager->isEnabled('microscope')) {
            // Use anonymous functions to get the instance from SM and call the methods
            $eventManager->attach(MvcEvent::EVENT_ROUTE, function (MvcEvent $event) use ($serviceManager) {
                $handler = $serviceManager->get(MicroscopeHandler::class);
                $handler->startProfiling($event);
            }, 1000);

            $eventManager->attach(MvcEvent::EVENT_DISPATCH, function (MvcEvent $event) use ($serviceManager) {
                $handler = $serviceManager->get(MicroscopeHandler::class);
                $handler->profileDispatch($event);
            }, 1000);
        }
    }

    // REMOVE the onError method entirely as it is no longer needed
    // public function onError(MvcEvent $e): void
    // {
    //     $serviceManager = $e->getApplication()->getServiceManager();
    //     $whoopsHandler = $serviceManager->get(\LaminasMicroscope\Whoops\WhoopsHandler::class);
    //     // This method does not exist and is not the correct way to trigger Whoops
    //     $whoopsHandler->handleError($e);
    // }

    // REMOVE onFinish, onRoute, onDispatch methods as listeners now point directly to handlers
    // public function onFinish(MvcEvent $e): void
    // {
    //     $serviceManager = $e->getApplication()->getServiceManager();
    //     // Use the class name directly for service retrieval
    //     $debugBar = $serviceManager->get(\LaminasMicroscope\DebugBar\DebugBarHandler::class);
    //     $debugBar->injectDebugBar($e);
    // }

    // REMOVE onRoute, onDispatch methods as listeners now point directly to handlers
    // public function onRoute(MvcEvent $e): void
    // {
    //     $serviceManager = $e->getApplication()->getServiceManager();
    //     // Use the class name directly for service retrieval
    //     $microscope = $serviceManager->get(\LaminasMicroscope\Microscope\MicroscopeHandler::class);
    //     $microscope->startProfiling($e);
    // }

    // public function onDispatch(MvcEvent $e): void
    // {
    //     $serviceManager = $e->getApplication()->getServiceManager();
    //     // Use the class name directly for service retrieval
    //     $microscope = $serviceManager->get(\LaminasMicroscope\Microscope\MicroscopeHandler::class);
    //     $microscope->profileDispatch($e);
    // }
}
