<?php

declare(strict_types=1);

namespace LaminasMicroscope\Listener;

use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Whoops\WhoopsHandler;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Event listener for Whoops error handling
 */
class WhoopsEventListener
{
    public function __construct(
        private ServiceManager $serviceManager,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Handle MVC errors (both dispatch and render errors)
     */
    public function onError(MvcEvent $event): void
    {
        try {
            $exception = $event->getParam('exception');
            
            if (!$exception instanceof Throwable) {
                return;
            }

            $whoopsHandler = $this->serviceManager->get(WhoopsHandler::class);
            
            if (!$whoopsHandler->shouldDisplay()) {
                return;
            }

            $whoopsRun = $whoopsHandler->getWhoops();
            
            if (!$whoopsRun) {
                $this->logError('Whoops handler not available', $exception);
                return;
            }

            // Clean output buffer before showing error page
            $this->cleanOutputBuffer();
            
            // Handle the exception with Whoops
            $whoopsRun->handleException($exception);
            
            // Stop event propagation to prevent default error handling
            $event->stopPropagation(true);
            $event->setResult($event->getResponse());
            
        } catch (Throwable $e) {
            $this->logError('Failed to handle error with Whoops', $e);
        }
    }

    /**
     * Clean all output buffers
     */
    private function cleanOutputBuffer(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
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
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);
        }
    }
}