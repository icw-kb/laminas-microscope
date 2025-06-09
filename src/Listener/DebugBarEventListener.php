<?php

declare(strict_types=1);

namespace LaminasMicroscope\Listener;

use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\DebugBar\DebugBarHandler;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Event listener for Debug Bar timing, injection, and logging
 */
class DebugBarEventListener
{
    private const TIMING_EVENTS = [
        'bootstrap_start',
        'route_start',
        'dispatch_start', 
        'render_start'
    ];

    public function __construct(
        private ServiceManager $serviceManager,
        private array &$eventTimestamps,
        private ?LoggerInterface $logger = null,
        private string $assetsRouteName = 'laminas-microscope/debugbar-assets'
    ) {
    }

    /**
     * Handle route event - start route timing and measure bootstrap
     */
    public function onRoute(MvcEvent $event): void
    {
        try {
            $debugBarHandler = $this->getDebugBarHandler();
            
            if (!$debugBarHandler || !$debugBarHandler->shouldDisplay()) {
                return;
            }

            $this->eventTimestamps['route_start'] = microtime(true);

            // Measure bootstrap duration if we recorded the start time
            if (isset($this->eventTimestamps['bootstrap_start'])) {
                $debugBarHandler->addMeasure(
                    'Bootstrap', 
                    $this->eventTimestamps['bootstrap_start'], 
                    microtime(true)
                );
                unset($this->eventTimestamps['bootstrap_start']);
            }

            $debugBarHandler->startTimer('route', 'Routing');
            
        } catch (Throwable $e) {
            $this->logError('Failed to handle route event in Debug Bar', $e);
        }
    }

    /**
     * Handle dispatch event - stop route timer, start dispatch timer
     */
    public function onDispatch(MvcEvent $event): void
    {
        try {
            if ($this->isAssetRequest($event)) {
                return;
            }

            $debugBarHandler = $this->getDebugBarHandler();
            
            if (!$debugBarHandler || !$debugBarHandler->shouldDisplay()) {
                return;
            }

            $this->eventTimestamps['dispatch_start'] = microtime(true);

            // Stop route timer if it was started
            if (isset($this->eventTimestamps['route_start'])) {
                $debugBarHandler->stopTimer('route');
                unset($this->eventTimestamps['route_start']);
            }

            $debugBarHandler->startTimer('dispatch', 'Dispatch');
            
        } catch (Throwable $e) {
            $this->logError('Failed to handle dispatch event in Debug Bar', $e);
        }
    }

    /**
     * Handle render event - stop dispatch timer, start render timer
     */
    public function onRender(MvcEvent $event): void
    {
        try {
            if ($this->isAssetRequest($event)) {
                return;
            }

            $debugBarHandler = $this->getDebugBarHandler();
            
            if (!$debugBarHandler || !$debugBarHandler->shouldDisplay()) {
                return;
            }

            $this->eventTimestamps['render_start'] = microtime(true);

            // Stop dispatch timer if it was started
            if (isset($this->eventTimestamps['dispatch_start'])) {
                $debugBarHandler->stopTimer('dispatch');
                unset($this->eventTimestamps['dispatch_start']);
            }

            $debugBarHandler->startTimer('render', 'Rendering');
            
        } catch (Throwable $e) {
            $this->logError('Failed to handle render event in Debug Bar', $e);
        }
    }

    /**
     * Handle finish event - stop render timer (timing phase)
     */
    public function onFinishTiming(MvcEvent $event): void
    {
        try {
            if ($this->isAssetRequest($event)) {
                return;
            }

            $debugBarHandler = $this->getDebugBarHandler();
            
            if (!$debugBarHandler || !$debugBarHandler->shouldDisplay()) {
                return;
            }

            // Stop render timer if it was started
            if (isset($this->eventTimestamps['render_start'])) {
                $debugBarHandler->stopTimer('render');
                unset($this->eventTimestamps['render_start']);
            }
            
        } catch (Throwable $e) {
            $this->logError('Failed to handle finish timing in Debug Bar', $e);
        }
    }

    /**
     * Handle finish event - inject debug bar into response
     */
    public function onFinishInject(MvcEvent $event): void
    {
        try {
            $handler = $this->getDebugBarHandler();
            
            if ($handler) {
                if (method_exists($handler, 'injectDebugBar')) {
                    $handler->injectDebugBar($event);
                } else {
                    // Fallback to static method if instance method doesn't exist
                    DebugBarHandler::injectDebugBar($event);
                }
            }
            
        } catch (Throwable $e) {
            $this->logError('Failed to inject Debug Bar', $e);
        }
    }

    /**
     * Log response headers and result at render
     */
    public function logResponseHeaders(MvcEvent $event): void
    {
        try {
            $handler = $this->getDebugBarHandler();
            
            if ($handler) {
                if (method_exists($handler, 'logResponseHeadersAndResultAtRender')) {
                    $handler->logResponseHeadersAndResultAtRender($event);
                } else {
                    DebugBarHandler::logResponseHeadersAndResultAtRender($event);
                }
            }
            
        } catch (Throwable $e) {
            $this->logError('Failed to log response headers in Debug Bar', $e);
        }
    }

    /**
     * Log MVC event result at dispatch
     */
    public function logMvcResult(MvcEvent $event): void
    {
        try {
            $handler = $this->getDebugBarHandler();
            
            if ($handler) {
                if (method_exists($handler, 'logMvcEventResultAtDispatch')) {
                    $handler->logMvcEventResultAtDispatch($event);
                } else {
                    DebugBarHandler::logMvcEventResultAtDispatch($event);
                }
            }
            
        } catch (Throwable $e) {
            $this->logError('Failed to log MVC result in Debug Bar', $e);
        }
    }

    /**
     * Log when asset route is matched (for debugging)
     */
    public function logAssetRoute(MvcEvent $event): void
    {
        if ($this->isAssetRequest($event) && $this->logger) {
            $this->logger->debug('Debug Bar asset route matched', [
                'route' => $this->assetsRouteName
            ]);
        }
    }

    /**
     * Record bootstrap start time
     */
    public function recordBootstrapStart(): void
    {
        $this->eventTimestamps['bootstrap_start'] = microtime(true);
    }

    /**
     * Get debug bar handler from service manager
     */
    private function getDebugBarHandler(): ?DebugBarHandler
    {
        try {
            return $this->serviceManager->get(DebugBarHandler::class);
        } catch (Throwable $e) {
            $this->logError('Failed to get DebugBarHandler from service manager', $e);
            return null;
        }
    }

    /**
     * Check if this is a request for debug bar assets
     */
    private function isAssetRequest(MvcEvent $event): bool
    {
        $routeMatch = $event->getRouteMatch();
        return $routeMatch && $routeMatch->getMatchedRouteName() === $this->assetsRouteName;
    }

    /**
     * Log error messages
     */
    private function logError(string $message, Throwable $exception): void
    {
        if ($this->logger) {
            $this->logger->error($message, [
                'exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]);
        }
    }
}