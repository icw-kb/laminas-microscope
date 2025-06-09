<?php

declare(strict_types=1);

namespace LaminasMicroscope;

use Laminas\ModuleManager\Feature\AutoloaderProviderInterface;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\ModuleManager\Feature\InitProviderInterface;
use Laminas\ModuleManager\Feature\BootstrapListenerInterface;
use Laminas\ModuleManager\ModuleManagerInterface;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Listener\DebugBarEventListener;
use LaminasMicroscope\Listener\MicroscopeEventListener;
use LaminasMicroscope\Listener\WhoopsEventListener;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Laminas Microscope Module
 * 
 * Provides debugging and profiling capabilities for Laminas applications
 * through Whoops error handling, Debug Bar profiling, and Microscope analysis.
 */
class Module implements
    AutoloaderProviderInterface,
    ConfigProviderInterface,
    InitProviderInterface,
    BootstrapListenerInterface
{
    // Event priorities
    private const PRIORITY_WHOOPS_ERROR = 100;
    private const PRIORITY_MICROSCOPE_ROUTE = 1000;
    private const PRIORITY_MICROSCOPE_DISPATCH = 1000;
    private const PRIORITY_MICROSCOPE_RENDER = 1;
    private const PRIORITY_MICROSCOPE_FINISH = -2000;
    private const PRIORITY_DEBUGBAR_ROUTE = 1001;
    private const PRIORITY_DEBUGBAR_DISPATCH = 1001;
    private const PRIORITY_DEBUGBAR_RENDER = 2;
    private const PRIORITY_DEBUGBAR_FINISH_TIMING = -900;
    private const PRIORITY_DEBUGBAR_FINISH_INJECT = -1000;
    private const PRIORITY_DEBUGBAR_LOG_RENDER = 1;
    private const PRIORITY_DEBUGBAR_LOG_DISPATCH = -1;
    private const PRIORITY_DEBUGBAR_LOG_ROUTE = -10000;
    
    private const DEBUGBAR_ASSETS_ROUTE = 'laminas-microscope/debugbar-assets';
    
    private array $eventTimestamps = [];
    private ?LoggerInterface $logger = null;

    public function init(ModuleManagerInterface $manager): void
    {
        // Early initialization for configuration loading
    }

    public function onBootstrap(EventInterface $e): void
    {
        try {
            $serviceManager = $e->getApplication()->getServiceManager();
            $this->logger = $this->getLogger($serviceManager);
            
            $componentManager = $serviceManager->get(ComponentManager::class);
            
            // Initialize all enabled components early in the bootstrap
            // This ensures Whoops\Run::register() is called if Whoops is enabled
            // and DebugBar is initialized to start its internal timers
            $componentManager->initializeAllComponents();
            
            // Record bootstrap start time for debug bar timing
            $this->recordBootstrapStartTime($componentManager);
            
            // Attach event listeners for enabled components
            $this->attachEventListeners($e, $componentManager);
            
        } catch (Throwable $exception) {
            $this->logError('Failed to bootstrap Laminas Microscope', $exception);
            // Don't break the application if microscope fails to initialize
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

    /**
     * Attach event listeners for enabled components
     */
    private function attachEventListeners(EventInterface $e, ComponentManager $componentManager): void
    {
        $eventManager = $e->getApplication()->getEventManager();
        $serviceManager = $e->getApplication()->getServiceManager();

        if ($componentManager->isEnabled('whoops')) {
            $this->attachWhoopsListeners($eventManager, $serviceManager);
        }

        if ($componentManager->isEnabled('microscope')) {
            $this->attachMicroscopeListeners($eventManager, $serviceManager);
        }

        if ($componentManager->isEnabled('debug_bar')) {
            $this->attachDebugBarListeners($eventManager, $serviceManager);
        }
    }

    /**
     * Attach Whoops error handling listeners
     */
    private function attachWhoopsListeners(EventManagerInterface $eventManager, ServiceManager $serviceManager): void
    {
        $listener = new WhoopsEventListener($serviceManager, $this->logger);
        
        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$listener, 'onError'], self::PRIORITY_WHOOPS_ERROR);
        $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, [$listener, 'onError'], self::PRIORITY_WHOOPS_ERROR);
    }

    /**
     * Attach Microscope profiling listeners
     */
    private function attachMicroscopeListeners(EventManagerInterface $eventManager, ServiceManager $serviceManager): void
    {
        $listener = new MicroscopeEventListener($serviceManager, $this->logger);
        
        $eventManager->attach(MvcEvent::EVENT_ROUTE, [$listener, 'onRoute'], self::PRIORITY_MICROSCOPE_ROUTE);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$listener, 'onDispatch'], self::PRIORITY_MICROSCOPE_DISPATCH);
        $eventManager->attach(MvcEvent::EVENT_RENDER, [$listener, 'onRender'], self::PRIORITY_MICROSCOPE_RENDER);
        $eventManager->attach(MvcEvent::EVENT_FINISH, [$listener, 'onFinish'], self::PRIORITY_MICROSCOPE_FINISH);
    }

    /**
     * Attach Debug Bar timing and injection listeners
     */
    private function attachDebugBarListeners(EventManagerInterface $eventManager, ServiceManager $serviceManager): void
    {
        $listener = new DebugBarEventListener(
            $serviceManager, 
            $this->eventTimestamps, 
            $this->logger,
            self::DEBUGBAR_ASSETS_ROUTE
        );
        
        // Timing listeners (higher priority than microscope to ensure accurate timing)
        $eventManager->attach(MvcEvent::EVENT_ROUTE, [$listener, 'onRoute'], self::PRIORITY_DEBUGBAR_ROUTE);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$listener, 'onDispatch'], self::PRIORITY_DEBUGBAR_DISPATCH);
        $eventManager->attach(MvcEvent::EVENT_RENDER, [$listener, 'onRender'], self::PRIORITY_DEBUGBAR_RENDER);
        $eventManager->attach(MvcEvent::EVENT_FINISH, [$listener, 'onFinishTiming'], self::PRIORITY_DEBUGBAR_FINISH_TIMING);
        
        // Debug bar injection and logging (separate priorities for different concerns)
        $eventManager->attach(MvcEvent::EVENT_FINISH, [$listener, 'onFinishInject'], self::PRIORITY_DEBUGBAR_FINISH_INJECT);
        $eventManager->attach(MvcEvent::EVENT_RENDER, [$listener, 'logResponseHeaders'], self::PRIORITY_DEBUGBAR_LOG_RENDER);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$listener, 'logMvcResult'], self::PRIORITY_DEBUGBAR_LOG_DISPATCH);
        $eventManager->attach(MvcEvent::EVENT_ROUTE, [$listener, 'logAssetRoute'], self::PRIORITY_DEBUGBAR_LOG_ROUTE);
    }

    /**
     * Record bootstrap start time for debug bar timing if debug bar is enabled
     */
    private function recordBootstrapStartTime(ComponentManager $componentManager): void
    {
        if ($componentManager->isEnabled('debug_bar')) {
            $this->eventTimestamps['bootstrap_start'] = microtime(true);
        }
    }

    /**
     * Get logger from service manager if available
     */
    private function getLogger(ServiceManager $serviceManager): ?LoggerInterface
    {
        try {
            if ($serviceManager->has(LoggerInterface::class)) {
                return $serviceManager->get(LoggerInterface::class);
            }
        } catch (Throwable $e) {
            // Logger not available, continue without logging
        }
        
        return null;
    }

    /**
     * Log error messages with context
     */
    private function logError(string $message, Throwable $exception): void
    {
        if ($this->logger) {
            $this->logger->error($message, [
                'exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);
        }
        
        // Fallback to error_log if no logger available (but only in debug mode)
        if (!$this->logger && getenv('APPLICATION_ENV') === 'development') {
            error_log(sprintf(
                'LaminasMicroscope Error: %s - %s in %s:%d',
                $message,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
        }
    }
}