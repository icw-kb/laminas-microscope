<?php

declare(strict_types=1);

namespace LaminasMicroscope\Listener;

use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Microscope\MicroscopeHandler;
use Psr\Log\LoggerInterface;
use Throwable;

use function method_exists;

/**
 * Event listener for Microscope profiling and analysis
 */
class MicroscopeEventListener
{
    public function __construct(
        private ServiceManager $serviceManager,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Handle route event - start profiling
     */
    public function onRoute(MvcEvent $event): void
    {
        try {
            $handler = $this->serviceManager->get(MicroscopeHandler::class);

            // Check if methods exist and call accordingly
            if (method_exists($handler, 'startProfiling')) {
                $handler->startProfiling($event);
            } else {
                // Fallback to static method if instance method doesn't exist
                MicroscopeHandler::startProfiling($event);
            }
        } catch (Throwable $e) {
            $this->logError('Failed to start Microscope profiling on route', $e);
        }
    }

    /**
     * Handle dispatch event - profile dispatch phase
     */
    public function onDispatch(MvcEvent $event): void
    {
        try {
            $handler = $this->serviceManager->get(MicroscopeHandler::class);

            if (method_exists($handler, 'profileDispatch')) {
                $handler->profileDispatch($event);
            } else {
                MicroscopeHandler::profileDispatch($event);
            }
        } catch (Throwable $e) {
            $this->logError('Failed to profile dispatch in Microscope', $e);
        }
    }

    /**
     * Handle render event - start render profiling
     */
    public function onRender(MvcEvent $event): void
    {
        try {
            $handler = $this->serviceManager->get(MicroscopeHandler::class);

            if (method_exists($handler, 'startRender')) {
                $handler->startRender($event);
            } else {
                MicroscopeHandler::startRender($event);
            }
        } catch (Throwable $e) {
            $this->logError('Failed to start render profiling in Microscope', $e);
        }
    }

    /**
     * Handle finish event - finalize profiling and analysis
     */
    public function onFinish(MvcEvent $event): void
    {
        try {
            $handler = $this->serviceManager->get(MicroscopeHandler::class);

            if (method_exists($handler, 'stopRender')) {
                $handler->stopRender($event);
            } else {
                MicroscopeHandler::stopRender($event);
            }

            if (method_exists($handler, 'finalizeProfiling')) {
                $handler->finalizeProfiling($event);
            } else {
                MicroscopeHandler::finalizeProfiling($event);
            }
        } catch (Throwable $e) {
            $this->logError('Failed to finalize Microscope profiling', $e);
        }
    }

    /**
     * Log error messages
     */
    private function logError(string $message, Throwable $exception): void
    {
        if ($this->logger) {
            $this->logger->error($message, [
                'exception' => $exception->getMessage(),
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
            ]);
        }
    }
}
