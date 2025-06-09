<?php

declare(strict_types=1);

namespace LaminasMicroscope\Whoops;

use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Contracts\HandlerInterface;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PlainTextHandler;
use Exception;

/**
 * Handler for Whoops error display integration
 */
class WhoopsHandler implements HandlerInterface
{
    private ?Run $whoops = null;
    private bool $initialized = false;

    public function __construct(
        private ConfigurationService $configService
    ) {}

    /**
     * Check if Whoops is enabled
     */
    public function isEnabled(): bool
    {
        if (!$this->configService->isEnabled()) {
            return false;
        }

        $config = $this->configService->getComponentConfig('whoops');
        return (bool) ($config['enabled'] ?? false);
    }

    /**
     * Initialize Whoops
     */
    public function initialize(): void
    {
        if ($this->initialized || !$this->isEnabled()) {
            return;
        }

        $this->whoops = new Run();
        $this->setupHandlers();
        $this->whoops->register(); // This registers Whoops with PHP's error handling
        $this->initialized = true;
    }

    /**
     * Get the Whoops instance
     */
    public function getWhoops(): ?Run
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return $this->whoops;
    }

    /**
     * Check if Whoops should be displayed in current environment
     */
    public function shouldDisplay(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $config = $this->configService->getComponentConfig('whoops');
        $environment = $this->configService->getEnvironment();

        // Don't show in production unless explicitly enabled
        if ($environment === 'production') {
            return (bool) ($config['show_in_production'] ?? false);
        }

        return true;
    }

    /**
     * Reset Whoops handler
     */
    public function reset(): void
    {
        if ($this->whoops) {
            $this->whoops->unregister();
            $this->whoops = null;
            $this->initialized = false;
        }
    }

    /**
     * Check if Whoops is initialized
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Setup default handlers based on configuration
     */
    private function setupHandlers(): void
    {
        if (!$this->whoops) {
            return;
        }

        $config = $this->configService->getComponentConfig('whoops');
        $handlers = $config['handlers'] ?? ['pretty', 'json'];

        foreach ($handlers as $handlerName) {
            $this->addHandler($handlerName);
        }
    }

    /**
     * Add a specific handler
     */
    private function addHandler(string $name): void
    {
        if (!$this->whoops) {
            return;
        }

        switch ($name) {
            case 'pretty':
                $handler = new PrettyPageHandler();
                $this->configurePrettyHandler($handler);
                $this->whoops->appendHandler($handler);
                break;
            case 'json':
                $handler = new JsonResponseHandler();
                $this->configureJsonHandler($handler);
                $this->whoops->appendHandler($handler);
                break;
            case 'plain':
                $handler = new PlainTextHandler();
                $this->whoops->appendHandler($handler);
                break;
        }
    }

    /**
     * Configure the pretty page handler
     */
    private function configurePrettyHandler(PrettyPageHandler $handler): void
    {
        $config = $this->configService->getComponentConfig('whoops');

        if (isset($config['editor'])) {
            $handler->setEditor($config['editor']);
        }

        if (isset($config['page_title'])) {
            $handler->setPageTitle($config['page_title']);
        }
    }

    /**
     * Configure the JSON response handler
     */
    private function configureJsonHandler(JsonResponseHandler $handler): void
    {
        $config = $this->configService->getComponentConfig('whoops');

        if (isset($config['json_api'])) {
            $handler->addTraceToOutput($config['json_api']);
        }
    }
}
