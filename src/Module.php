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
use LaminasMicroscope\Registry;
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
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: Module::onBootstrap triggered.\n"); // Corrected newline
        // --- END DEBUG LOGGING ---

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
            Registry::setDebugBar($debugBarHandler);
            // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: Module::onBootstrap - Retrieved DebugBarHandler instance: " . spl_object_hash($debugBarHandler) . ".\n"); // Log instance hash
            // --- END DEBUG LOGGING ---

            if ($debugBarHandler->shouldDisplay()) {
                 // --- DEBUG LOGGING ---
                 error_log("LaminasMicroscope: DEBUG: Module::onBootstrap - Debug Bar enabled and should display. Recording bootstrap start time.\n"); // Added log
                 // --- END DEBUG LOGGING ---
                 $this->eventTimestamps['bootstrap_start'] = microtime(true);
            } else {
                 // --- DEBUG LOGGING ---
                 error_log("LaminasMicroscope: DEBUG: Module::onBootstrap - Debug Bar enabled but should NOT display. Skipping bootstrap timer recording.\n"); // Added log
                 // --- END DEBUG LOGGING ---
            }
        } else {
             // --- DEBUG LOGGING ---
             error_log("LaminasMicroscope: DEBUG: Module::onBootstrap - Debug Bar component is NOT enabled. Skipping bootstrap timer recording.\n"); // Added log
             // --- END DEBUG LOGGING ---
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
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: Module::attachEventListeners triggered.\n"); // Corrected newline
        // --- END DEBUG LOGGING ---

        $eventManager = $e->getApplication()->getEventManager();
        $serviceManager = $e->getApplication()->getServiceManager();

        // Whoops error handling
        if ($componentManager->isEnabled('whoops')) {
            // Listener to catch DISPATCH_ERROR and trigger Whoops
            $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, function (MvcEvent $event) use ($serviceManager) {
                $exception = $event->getParam('exception');
                // \Throwable is correct PHP syntax for the global interface
                if ($exception instanceof \Throwable) {
                    $whoopsHandler = $serviceManager->get(WhoopsHandler::class);
                    if ($whoopsHandler->shouldDisplay()) {
                         $whoopsRun = $whoopsHandler->getWhoops();
                         if ($whoopsRun) {
                             // Clear any previous output buffer to ensure Whoops can render
                             while (ob_get_level() > 0) {
                                 ob_end_clean();
                             }
                             $whoopsRun->handleException($exception);
                             // Stop propagation and prevent further rendering/dispatch
                             $event->stopPropagation(true);
                             // Whoops handles setting the response content and status code internally
                             // We just need to ensure the event result is the response
                             $event->setResult($event->getResponse());
                             return $event->getResponse();
                         }
                    }
                }
            }, 100); // High priority to catch errors early

            // Listener to catch RENDER_ERROR and trigger Whoops
            $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, function (MvcEvent $event) use ($serviceManager) {
                $exception = $event->getParam('exception');
                // \Throwable is correct PHP syntax for the global interface
                if ($exception instanceof \Throwable) {
                     $whoopsHandler = $serviceManager->get(WhoopsHandler::class);
                     if ($whoopsHandler->shouldDisplay()) {
                         $whoopsRun = $whoopsHandler->getWhoops();
                         if ($whoopsRun) {
                             // Clear any previous output buffer
                             while (ob_get_level() > 0) {
                                 ob_end_clean();
                             }
                             $whoopsRun->handleException($exception);
                             // Stop propagation and prevent further rendering
                             $event->stopPropagation(true);
                             // Whoops handles setting the response content and status code internally
                             // We just need to ensure the event result is the response
                             $event->setResult($event->getResponse());
                             return $event->getResponse();
                         }
                     }
                }
            }, 100); // High priority
        }

        // Microscope profiling
        if ($componentManager->isEnabled('microscope')) {
            // Listener to record route start timestamp and details
            $eventManager->attach(MvcEvent::EVENT_ROUTE, function (MvcEvent $event) use ($serviceManager) {
                $handler = $serviceManager->get(MicroscopeHandler::class);
                // startProfiling is now static and takes the event
                MicroscopeHandler::startProfiling($event); // This records route_start timestamp and route details
            }, 1000);

            // Listener to record dispatch end timestamp
            $eventManager->attach(MvcEvent::EVENT_DISPATCH, function (MvcEvent $event) use ($serviceManager) {
                $handler = $serviceManager->get(MicroscopeHandler::class);
                 // profileDispatch is now static and takes the event
                MicroscopeHandler::profileDispatch($event); // This records dispatch_end timestamp
            }, 1000); // High priority to run late in DISPATCH (after controller returns result)

            // Listener to record render start timestamp
            $eventManager->attach(MvcEvent::EVENT_RENDER, function (MvcEvent $event) use ($serviceManager) {
                $handler = $serviceManager->get(MicroscopeHandler::class);
                 // startRender is now static and takes the event
                MicroscopeHandler::startRender($event); // This records render_start timestamp
            }, 1); // Low priority to run early in RENDER

            // Listener to record render end and finalize profiling
            $eventManager->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $event) use ($serviceManager) {
                $handler = $serviceManager->get(MicroscopeHandler::class);
                 // stopRender and finalizeProfiling are now static and take the event
                MicroscopeHandler::stopRender($event); // This records render_end timestamp
                MicroscopeHandler::finalizeProfiling($event); // This records request_end and calculates breakdown
            }, -2000); // Run after DebugBar injection (-1000)
        }

        // Debug bar
        if ($componentManager->isEnabled('debug_bar')) {
            $debugBarHandler = $serviceManager->get(DebugBarHandler::class);
            // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: Module::attachEventListeners - Retrieved DebugBarHandler instance: " . spl_object_hash($debugBarHandler) . ".\n"); // Log instance hash
            // --- END DEBUG LOGGING ---


            // Record route start time and add bootstrap measure
            $eventManager->attach(MvcEvent::EVENT_ROUTE, function (MvcEvent $event) use ($debugBarHandler) {
                 $routeMatch = $event->getRouteMatch();

                 // --- DEBUG LOGGING ---
                 error_log("LaminasMicroscope: DEBUG: Module::EVENT_ROUTE listener triggered. RouteMatch: " . ($routeMatch ? $routeMatch->getMatchedRouteName() : 'null') . ".\n"); // Added log
                 error_log("LaminasMicroscope: DEBUG: Module::EVENT_ROUTE listener - DebugBarHandler instance: " . spl_object_hash($debugBarHandler) . ".\n"); // Log instance hash
                 // --- END DEBUG LOGGING ---

                 if ($debugBarHandler->shouldDisplay()) {
                     // Record route start time
                     $this->eventTimestamps['route_start'] = microtime(true);

                     // Add bootstrap measure if start time was recorded
                     if (isset($this->eventTimestamps['bootstrap_start'])) {
                         $bootstrapDuration = (microtime(true) - $this->eventTimestamps['bootstrap_start']) * 1000; // ms
                         // --- DEBUG LOGGING ---
                         error_log("LaminasMicroscope: DEBUG: Module::EVENT_ROUTE listener - Adding bootstrap measure: " . $bootstrapDuration . "ms.\n"); // Added log
                         // --- END DEBUG LOGGING ---
                         $debugBarHandler->addMeasure('Bootstrap', $this->eventTimestamps['bootstrap_start'], microtime(true));
                         unset($this->eventTimestamps['bootstrap_start']); // Clear the start time
                     } else {
                         // --- DEBUG LOGGING ---
                         error_log("LaminasMicroscope: DEBUG: Module::EVENT_ROUTE listener - Bootstrap start time not recorded. Cannot add bootstrap measure.\n"); // Added log
                         // --- END DEBUG LOGGING ---
                     }

                     // Start Route timer
                     // --- DEBUG LOGGING ---
                     error_log("LaminasMicroscope: DEBUG: Module::EVENT_ROUTE listener - Calling startTimer('route').\n"); // Added log
                     // --- END DEBUG LOGGING ---
                     $debugBarHandler->startTimer('route', 'Routing');
                     // --- DEBUG LOGGING ---
                     error_log("LaminasMicroscope: DEBUG: Module::EVENT_ROUTE listener - Called startTimer('route').\n"); // Added log
                     // --- END DEBUG LOGGING ---

                 } else {
                     // --- DEBUG LOGGING ---
                     error_log("LaminasMicroscope: DEBUG: Module::EVENT_ROUTE listener - Debug Bar should NOT display. Skipping timer recording.\n"); // Added log
                     // --- END DEBUG LOGGING ---
                 }
            }, 1001); // Higher priority than Microscope's ROUTE listener

            // Record dispatch start time and add route measure
            $eventManager->attach(MvcEvent::EVENT_DISPATCH, function (MvcEvent $event) use ($debugBarHandler) {
                 $routeMatch = $event->getRouteMatch();
                 // Skip if this is the asset route
                 if ($routeMatch && $routeMatch->getMatchedRouteName() === 'laminas-microscope/debugbar-assets') {
                     return;
                 }
                 if ($debugBarHandler->shouldDisplay()) {
                     // Record dispatch start time
                     $this->eventTimestamps['dispatch_start'] = microtime(true);

                     // Add route measure if start time was recorded
                     if (isset($this->eventTimestamps['route_start'])) {
                         $routeDuration = (microtime(true) - $this->eventTimestamps['route_start']) * 1000; // ms
                         // --- DEBUG LOGGING ---
                         error_log("LaminasMicroscope: DEBUG: Module::EVENT_DISPATCH listener - Adding route measure: " . $routeDuration . "ms.\n"); // Added log
                         // --- END DEBUG LOGGING ---
                         $debugBarHandler->stopTimer('route'); // Stop the route timer started in EVENT_ROUTE
                         unset($this->eventTimestamps['route_start']); // Clear the start time
                     } else {
                         // --- DEBUG LOGGING ---
                         error_log("LaminasMicroscope: DEBUG: Module::EVENT_DISPATCH listener - Route start time not recorded. Cannot add route measure.\n"); // Added log
                         // --- END DEBUG LOGGING ---
                     }

                     // Start Dispatch timer
                     // --- DEBUG LOGGING ---
                     error_log("LaminasMicroscope: DEBUG: Module::EVENT_DISPATCH listener - Calling startTimer('dispatch').\n"); // Added log
                     // --- END DEBUG LOGGING ---
                     $debugBarHandler->startTimer('dispatch', 'Dispatch');
                     // --- DEBUG LOGGING ---
                     error_log("LaminasMicroscope: DEBUG: Module::EVENT_DISPATCH listener - Called startTimer('dispatch').\n"); // Added log
                     // --- END DEBUG LOGGING ---
                 }
            }, 1001); // Higher priority than Microscope's DISPATCH listener

            // Record render start time and add dispatch measure
            $eventManager->attach(MvcEvent::EVENT_RENDER, function (MvcEvent $event) use ($debugBarHandler) {
                 $routeMatch = $event->getRouteMatch();
                 // Skip if this is the asset route
                 if ($routeMatch && $routeMatch->getMatchedRouteName() === 'laminas-microscope/debugbar-assets') {
                     return;
                 }
                 if ($debugBarHandler->shouldDisplay()) {
                     // Record render start time
                     $this->eventTimestamps['render_start'] = microtime(true);

                     // Add dispatch measure if start time was recorded
                     if (isset($this->eventTimestamps['dispatch_start'])) {
                         $dispatchDuration = (microtime(true) - $this->eventTimestamps['dispatch_start']) * 1000; // ms
                         // --- DEBUG LOGGING ---
                         error_log("LaminasMicroscope: DEBUG: Module::EVENT_RENDER listener - Adding dispatch measure: " . $dispatchDuration . "ms.\n"); // Added log
                         // --- END DEBUG LOGGING ---
                         $debugBarHandler->stopTimer('dispatch'); // Stop the dispatch timer started in EVENT_DISPATCH
                         unset($this->eventTimestamps['dispatch_start']); // Clear the start time
                     } else {
                         // --- DEBUG LOGGING ---
                         error_log("LaminasMicroscope: DEBUG: Module::EVENT_RENDER listener - Dispatch start time not recorded. Cannot add dispatch measure.\n"); // Added log
                         // --- END DEBUG LOGGING ---
                     }

                     // Start Render timer
                     // --- DEBUG LOGGING ---
                     error_log("LaminasMicroscope: DEBUG: Module::EVENT_RENDER listener - Calling startTimer('render').\n"); // Added log
                     // --- END DEBUG LOGGING ---
                     $debugBarHandler->startTimer('render', 'Rendering');
                     // --- DEBUG LOGGING ---
                     error_log("LaminasMicroscope: DEBUG: Module::EVENT_RENDER listener - Called startTimer('render').\n"); // Added log
                     // --- END DEBUG LOGGING ---
                 }
            }, 2); // Higher priority than Microscope's RENDER listener

            // Add render measure and finalize timers
            $eventManager->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $event) use ($serviceManager, $debugBarHandler) {
                 $routeMatch = $event->getRouteMatch();
                 // Skip if this is the asset route
                 if ($routeMatch && $routeMatch->getMatchedRouteName() === 'laminas-microscope/debugbar-assets') {
                     return;
                 }
                 if ($debugBarHandler->shouldDisplay()) {
                     // Add render measure if start time was recorded
                     if (isset($this->eventTimestamps['render_start'])) {
                         $renderDuration = (microtime(true) - $this->eventTimestamps['render_start']) * 1000; // ms
                         // --- DEBUG LOGGING ---
                         error_log("LaminasMicroscope: DEBUG: Module::EVENT_FINISH listener - Adding render measure: " . $renderDuration . "ms.\n"); // Added log
                         // --- END DEBUG LOGGING ---
                         $debugBarHandler->stopTimer('render'); // Stop the render timer started in EVENT_RENDER
                         unset($this->eventTimestamps['render_start']); // Clear the start time
                     } else {
                         // --- DEBUG LOGGING ---
                         error_log("LaminasMicroscope: DEBUG: Module::EVENT_FINISH listener - Render start time not recorded. Cannot add render measure.\n"); // Added log
                         // --- END DEBUG LOGGING ---
                     }

                     // The TimeDataCollector should automatically calculate the total time
                     // based on the first start and last stop/measure.
                     // We can explicitly add a measure for the total request time if needed,
                     // but the collector usually handles this.
                     // Let's add a log to see the final timers in the collector.
                     // --- DEBUG LOGGING ---
                     error_log("LaminasMicroscope: DEBUG: Module::EVENT_FINISH listener - Finalizing timers.\n"); // Added log
                     // --- END DEBUG LOGGING ---
                 }
            }, -900); // Run before DebugBar injection (-1000)


            // Listener to inject the debug bar at the end of the request
            $eventManager->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $event) use ($serviceManager) {
                // --- DEBUG LOGGING ---
                error_log("LaminasMicroscope: DEBUG: Module::onFinish listener triggered.\n"); // Corrected newline
                // --- END DEBUG LOGGING ---

                try {
                    $handler = $serviceManager->get(DebugBarHandler::class);
                    // --- DEBUG LOGGING ---
                    error_log("LaminasMicroscope: DEBUG: Module::onFinish listener - DebugBarHandler instance: " . spl_object_hash($handler) . ".\n"); // Log instance hash
                    // --- END DEBUG LOGGING ---

                    // --- DEBUG LOGGING ---
                    error_log("LaminasMicroscope: DEBUG: DebugBarHandler service retrieved successfully.\n"); // Corrected newline
                    error_log("LaminasMicroscope: DEBUG: Calling injectDebugBar().\n"); // Corrected newline
                    // --- END DEBUG LOGGING ---

                    // injectDebugBar is now static and takes the event
                    DebugBarHandler::injectDebugBar($event);

                    // --- DEBUG LOGGING ---
                    error_log("LaminasMicroscope: DEBUG: injectDebugBar() finished.\n"); // Corrected newline
                    // --- END DEBUG LOGGING ---

                } catch (\Exception $e) {
                    // --- DEBUG LOGGING ---
                    // \n is correct PHP syntax for a newline in a double-quoted string
                    // \Exception is correct PHP syntax for the global class
                    error_log("LaminasMicroscope: ERROR: Failed to retrieve or inject DebugBarHandler in onFinish: " . $e->getMessage() . "\n"); // Corrected newline
                    // --- END DEBUG LOGGING ---
                }
            }, -1000); // High priority to run late

            // Listener to log response headers and result type at the RENDER event
            $eventManager->attach(MvcEvent::EVENT_RENDER, function (MvcEvent $event) use ($serviceManager) {
                 // We don't need the handler instance here, just the static method
                 DebugBarHandler::logResponseHeadersAndResultAtRender($event);
            }, 1); // Low priority to run early in RENDER

            // Listener to log MvcEvent result type at the DISPATCH event
            $eventManager->attach(MvcEvent::EVENT_DISPATCH, function (MvcEvent $event) use ($serviceManager) {
                 // We don't need the handler instance here, just the static method
                 DebugBarHandler::logMvcEventResultAtDispatch($event);
            }, -1); // High priority to run late in DISPATCH (after controller returns result)

            // --- DEBUG LOGGING FOR ASSET ROUTE (Keep this for now) ---
            // Listener specifically for the assets route to see if it's matched
            $eventManager->attach(MvcEvent::EVENT_ROUTE, function (MvcEvent $event) {
                $routeMatch = $event->getRouteMatch();
                if ($routeMatch && $routeMatch->getMatchedRouteName() === 'laminas-microscope/debugbar-assets') {
                    // \n is correct PHP syntax for a newline in a double-quoted string
                    error_log("LaminasMicroscope: DEBUG: ROUTE matched for debugbar-assets!\n"); // Corrected newline
                }
            }, -10000); // Very low priority to run after normal routing
            // --- END DEBUG LOGGING ---
        }
    }
}
