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
use LaminasMicroscope\DebugBar\DebugBarHandler;
use LaminasMicroscope\Microscope\MicroscopeHandler;
use LaminasMicroscope\Whoops\WhoopsHandler;
use Closure;

class Module implements
    AutoloaderProviderInterface,
    ConfigProviderInterface,
    InitProviderInterface,
    BootstrapListenerInterface
{
    // Store timestamps for timing phases
    private array $eventTimestamps = [];

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
        // and DebugBar is initialized to start its internal timers.
        $componentManager->initializeAllComponents();

        // Only attach listeners for enabled components
        $this->attachEventListeners($e, $componentManager);

        // Record the start time of the bootstrap phase
        if ($componentManager->isEnabled('debug_bar')) {
            $debugBarHandler = $serviceManager->get(DebugBarHandler::class);

            if ($debugBarHandler->shouldDisplay()) {
                 $this->eventTimestamps['bootstrap_start'] = microtime(true);
            } else {
            }
        } else {
        }
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
        $serviceManager = $e->getApplication()->getServiceManager();

        if ($componentManager->isEnabled('whoops')) {
            $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, function (MvcEvent $event) use ($serviceManager) {
                $exception = $event->getParam('exception');
                if ($exception instanceof \Throwable) {
                    $whoopsHandler = $serviceManager->get(WhoopsHandler::class);
                    if ($whoopsHandler->shouldDisplay()) {
                         $whoopsRun = $whoopsHandler->getWhoops();
                         if ($whoopsRun) {
                             while (ob_get_level() > 0) {
                                 ob_end_clean();
                             }
                             $whoopsRun->handleException($exception);
                             $event->stopPropagation(true);
                             $event->setResult($event->getResponse());
                             return $event->getResponse();
                         }
                    }
                }
            }, 100); 

            $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, function (MvcEvent $event) use ($serviceManager) {
                $exception = $event->getParam('exception');
                if ($exception instanceof \Throwable) {
                     $whoopsHandler = $serviceManager->get(WhoopsHandler::class);
                     if ($whoopsHandler->shouldDisplay()) {
                         $whoopsRun = $whoopsHandler->getWhoops();
                         if ($whoopsRun) {
                             while (ob_get_level() > 0) {
                                 ob_end_clean();
                             }
                             $whoopsRun->handleException($exception);
                             $event->stopPropagation(true);
                             $event->setResult($event->getResponse());
                             return $event->getResponse();
                         }
                     }
                }
            }, 100); 
        }

        if ($componentManager->isEnabled('microscope')) {
            $eventManager->attach(MvcEvent::EVENT_ROUTE, function (MvcEvent $event) use ($serviceManager) {
                $handler = $serviceManager->get(MicroscopeHandler::class);
                MicroscopeHandler::startProfiling($event); 
            }, 1000);

            $eventManager->attach(MvcEvent::EVENT_DISPATCH, function (MvcEvent $event) use ($serviceManager) {
                $handler = $serviceManager->get(MicroscopeHandler::class);
                MicroscopeHandler::profileDispatch($event); // This records dispatch_end timestamp
            }, 1000); 

            $eventManager->attach(MvcEvent::EVENT_RENDER, function (MvcEvent $event) use ($serviceManager) {
                $handler = $serviceManager->get(MicroscopeHandler::class);
                MicroscopeHandler::startRender($event);
            }, 1); 

            $eventManager->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $event) use ($serviceManager) {
                $handler = $serviceManager->get(MicroscopeHandler::class);
                MicroscopeHandler::stopRender($event);
                MicroscopeHandler::finalizeProfiling($event); 
            }, -2000);
        }

        if ($componentManager->isEnabled('debug_bar')) {
            $debugBarHandler = $serviceManager->get(DebugBarHandler::class);


            $eventManager->attach(MvcEvent::EVENT_ROUTE, function (MvcEvent $event) use ($debugBarHandler) {
                 $routeMatch = $event->getRouteMatch();

                 if ($debugBarHandler->shouldDisplay()) {
                     $this->eventTimestamps['route_start'] = microtime(true);

                     if (isset($this->eventTimestamps['bootstrap_start'])) {
                         $bootstrapDuration = (microtime(true) - $this->eventTimestamps['bootstrap_start']) * 1000; // ms
                         $debugBarHandler->addMeasure('Bootstrap', $this->eventTimestamps['bootstrap_start'], microtime(true));
                         unset($this->eventTimestamps['bootstrap_start']); // Clear the start time
                     } else {
                     }

                     error_log("LaminasMicroscope: DEBUG: Module::EVENT_ROUTE listener - Calling startTimer('route').\n"); // Added log
                     $debugBarHandler->startTimer('route', 'Routing');
                     error_log("LaminasMicroscope: DEBUG: Module::EVENT_ROUTE listener - Called startTimer('route').\n"); // Added log

                 } else {
                     error_log("LaminasMicroscope: DEBUG: Module::EVENT_ROUTE listener - Debug Bar should NOT display. Skipping timer recording.\n"); // Added log
                 }
            }, 1001); // Higher priority than Microscope's ROUTE listener

            $eventManager->attach(MvcEvent::EVENT_DISPATCH, function (MvcEvent $event) use ($debugBarHandler) {
                 $routeMatch = $event->getRouteMatch();
                 if ($routeMatch && $routeMatch->getMatchedRouteName() === 'laminas-microscope/debugbar-assets') {
                     return;
                 }
                 if ($debugBarHandler->shouldDisplay()) {
                     $this->eventTimestamps['dispatch_start'] = microtime(true);

                     if (isset($this->eventTimestamps['route_start'])) {
                         $routeDuration = (microtime(true) - $this->eventTimestamps['route_start']) * 1000; // ms
                         $debugBarHandler->stopTimer('route'); // Stop the route timer started in EVENT_ROUTE
                         unset($this->eventTimestamps['route_start']); // Clear the start time
                     } else {
                     }

                     $debugBarHandler->startTimer('dispatch', 'Dispatch');
                 }
            }, 1001); // Higher priority than Microscope's DISPATCH listener

            $eventManager->attach(MvcEvent::EVENT_RENDER, function (MvcEvent $event) use ($debugBarHandler) {
                 $routeMatch = $event->getRouteMatch();
                 if ($routeMatch && $routeMatch->getMatchedRouteName() === 'laminas-microscope/debugbar-assets') {
                     return;
                 }
                 if ($debugBarHandler->shouldDisplay()) {
                     $this->eventTimestamps['render_start'] = microtime(true);

                     if (isset($this->eventTimestamps['dispatch_start'])) {
                         $dispatchDuration = (microtime(true) - $this->eventTimestamps['dispatch_start']) * 1000; // ms
                         $debugBarHandler->stopTimer('dispatch'); // Stop the dispatch timer started in EVENT_DISPATCH
                         unset($this->eventTimestamps['dispatch_start']); // Clear the start time
                     } else {
                     }

                     $debugBarHandler->startTimer('render', 'Rendering');
                 }
            }, 2); // Higher priority than Microscope's RENDER listener

            $eventManager->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $event) use ($serviceManager, $debugBarHandler) {
                 $routeMatch = $event->getRouteMatch();
                 if ($routeMatch && $routeMatch->getMatchedRouteName() === 'laminas-microscope/debugbar-assets') {
                     return;
                 }
                 if ($debugBarHandler->shouldDisplay()) {
                     if (isset($this->eventTimestamps['render_start'])) {
                         $renderDuration = (microtime(true) - $this->eventTimestamps['render_start']) * 1000; // ms
                         $debugBarHandler->stopTimer('render'); // Stop the render timer started in EVENT_RENDER
                         unset($this->eventTimestamps['render_start']); // Clear the start time
                     } else {
                     }

                 }
            }, -900); // Run before DebugBar injection (-1000)


            $eventManager->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $event) use ($serviceManager) {

                try {
                    $handler = $serviceManager->get(DebugBarHandler::class);
                    DebugBarHandler::injectDebugBar($event);

                } catch (\Exception $e) {
                }
            }, -1000); // High priority to run late

            $eventManager->attach(MvcEvent::EVENT_RENDER, function (MvcEvent $event) use ($serviceManager) {
                 DebugBarHandler::logResponseHeadersAndResultAtRender($event);
            }, 1); // Low priority to run early in RENDER

            $eventManager->attach(MvcEvent::EVENT_DISPATCH, function (MvcEvent $event) use ($serviceManager) {
                 DebugBarHandler::logMvcEventResultAtDispatch($event);
            }, -1); // High priority to run late in DISPATCH (after controller returns result)

            $eventManager->attach(MvcEvent::EVENT_ROUTE, function (MvcEvent $event) {
                $routeMatch = $event->getRouteMatch();
                if ($routeMatch && $routeMatch->getMatchedRouteName() === 'laminas-microscope/debugbar-assets') {
                    error_log("LaminasMicroscope: DEBUG: ROUTE matched for debugbar-assets!\n"); // Corrected newline
                }
            }, -10000); // Very low priority to run after normal routing
        }
    }
}
