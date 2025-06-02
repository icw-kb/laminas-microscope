<?php

declare(strict_types=1);

namespace LaminasMicroscope\DebugBar;

use LaminasMicroscope\Config\ConfigurationService;
use DebugBar\StandardDebugBar;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use Laminas\Mvc\MvcEvent;
use Laminas\Http\Response;
use Laminas\View\Model\ViewModel;
use Laminas\View\ViewManager;
use Laminas\View\View;
use Laminas\View\Resolver\TemplateMapResolver;
use Laminas\View\Resolver\TemplatePathStack;
use Laminas\EventManager\EventManagerInterface;
use Exception;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;
use Laminas\View\Renderer\RendererInterface;
use DebugBar\JavascriptRenderer;

/**
 * Handler for DebugBar integration
 */
class DebugBarHandler
{
    private ?StandardDebugBar $debugBar = null;
    private bool $initialized = false;
    private ContainerInterface $container;
    private ?JavascriptRenderer $renderer = null;

    public function __construct(
        private ConfigurationService $configService,
        ContainerInterface $container
    ) {
        $this->container = $container;
    }

    /**
     * Check if DebugBar is enabled
     */
    public function isEnabled(): bool
    {
        if (!$this->configService->isEnabled()) {
            return false;
        }

        $config = $this->configService->getComponentConfig('debug_bar');
        return (bool) ($config['enabled'] ?? false);
    }

    /**
     * Initialize the DebugBar
     */
    public function initialize(): void
    {
        if ($this->initialized || !$this->isEnabled()) {
            return;
        }

        $this->debugBar = new StandardDebugBar();
        $this->setupCollectors();

        $this->renderer = $this->debugBar->getJavascriptRenderer();
        $config = $this->configService->getComponentConfig('debug_bar');
        $baseUrl = $config['base_url'] ?? '/debugbar';

        // REMOVED: echo "LaminasMicroscope: DebugBarHandler::initialize - Configured base_url: " . $baseUrl . "\n";

        if ($this->renderer && method_exists($this->renderer, 'setBaseUrl')) {
            $this->renderer->setBaseUrl($baseUrl);
            // REMOVED: Debug logs for renderer base_url after set
        } else {
            // REMOVED: Debug logs for renderer being null or missing method
        }

        $this->initialized = true;
    }

    /**
     * Get the DebugBar instance
     */
    public function getDebugBar(): ?StandardDebugBar
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return $this->debugBar;
    }

    /**
     * Get the DebugBar renderer
     */
    public function getRenderer(): ?JavascriptRenderer
    {
        if (!$this->initialized) {
            $this->initialize();
        }
        return $this->renderer;
    }

    /**
     * Add a message to the debug bar
     */
    public function addMessage(string $message, string $label = 'info'): void
    {
        $debugBar = $this->getDebugBar();
        if ($debugBar && $debugBar->hasCollector('messages')) {
            $debugBar->getCollector('messages')->addMessage($message, $label);
        }
    }

    /**
     * Start a timer
     */
    public function startTimer(string $name, ?string $label = null): void
    {
        $debugBar = $this->getDebugBar();
        if ($debugBar && $debugBar->hasCollector('time')) {
            $debugBar->getCollector('time')->startMeasure($name, $label);
        }
    }

    /**
     * Stop a timer
     */
    public function stopTimer(string $name): void
    {
        $debugBar = $this->getDebugBar();
        if ($debugBar && $debugBar->hasCollector('time')) {
            $debugBar->getCollector('time')->stopMeasure($name);
        }
    }

    /**
     * Add custom data to a collector
     */
    public function addData(string $collector, string $key, mixed $value): void
    {
        $debugBar = $this->getDebugBar();
        if ($debugBar && $debugBar->hasCollector($collector)) {
            $collectorInstance = $debugBar->getCollector($collector);
            if (method_exists($collectorInstance, 'addData')) {
                $collectorInstance->addData($key, $value);
            }
        }
    }

    /**
     * Get collected data
     */
    public function getData(): array
    {
        $debugBar = $this->getDebugBar();
        if (!$debugBar) {
            return [];
        }

        return $debugBar->getData();
    }

    /**
     * Render the debug bar HTML
     */
    public function renderHtml(): string
    {
        $renderer = $this->getRenderer();
        if (!$renderer) {
            return '';
        }

        return $renderer->renderHead() . $renderer->render();
    }

    /**
     * Get the debug bar assets (CSS/JS)
     */
    public function getAssets(): array
    {
        $renderer = $this->getRenderer();
        if (!$renderer) {
            return ['css' => [], 'js' => []];
        }

        $assets = [
            'css' => [],
            'js' => [],
        ];

        try {
            $headOutput = $renderer->renderHead();
            if (!empty($headOutput)) {
                $assets['css'][] = 'embedded';
            }

            $jsOutput = $renderer->render();
            if (!empty($jsOutput)) {
                $assets['js'][] = 'embedded';
            }

            if (method_exists($renderer, 'getAssets')) {
                $rendererAssets = $renderer->getAssets();
                if (isset($rendererAssets['css'])) {
                    $assets['css'] = array_merge($assets['css'], $rendererAssets['css']);
                }
                if (isset($rendererAssets['js'])) {
                    $assets['js'] = array_merge($assets['js'], $rendererAssets['js']);
                }
            }

            if (method_exists($renderer, 'getCssAssets')) {
                $assets['css'] = array_merge($assets['css'], $renderer->getCssAssets());
            }
            if (method_exists( $renderer, 'getJsAssets')) {
                $assets['js'] = array_merge($assets['js'], $renderer->getJsAssets());
            }

        } catch (Exception $e) {
            $assets = [
                'css' => ['debugbar-embedded'],
                'js' => ['debugbar-embedded'],
            ];
        }

        return $assets;
    }

    /**
     * Check if DebugBar should be displayed in current environment
     */
    public function shouldDisplay(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $config = $this->configService->getComponentConfig('debug_bar');
        $environment = $this->configService->getEnvironment();

        if ($environment === 'production') {
            $showInProduction = (bool) ($config['show_in_production'] ?? false);
            return $showInProduction;
        }

        return true;
    }

    /**
     * Setup default collectors
     */
    private function setupCollectors(): void
    {
        if (!$this->debugBar) {
            return;
        }

        $config = $this->configService->getComponentConfig('debug_bar');
        $collectors = $config['collectors'] ?? ['time', 'memory', 'messages'];

        foreach ($collectors as $collectorName) {
            $this->addCollector($collectorName);
        }
    }

    /**
     * Add a specific collector
     */
    private function addCollector(string $name): void
    {
        if (!$this->debugBar) {
            return;
        }

        switch ($name) {
            case 'time':
                if (!$this->debugBar->hasCollector('time')) {
                    try {
                        $this->debugBar->addCollector(new TimeDataCollector());
                    } catch (Exception $e) {
                        // Silently ignore if already exists
                    }
                }
                break;

            case 'memory':
                if (!$this->debugBar->hasCollector('memory')) {
                    try {
                        $this->debugBar->addCollector(new MemoryCollector());
                    } catch (Exception $e) {
                        // Silently ignore if already exists
                    }
                }
                break;

            case 'messages':
                if (!$this->debugBar->hasCollector('messages')) {
                    try {
                        $this->debugBar->addCollector(new MessagesCollector());
                    } catch (Exception $e) {
                        // Silently ignore if already exists
                    }
                }
                break;

            case 'phpinfo':
            case 'php':
                $customKey = 'microscope_php';
                if (!$this->debugBar->hasCollector($customKey)) {
                    try {
                        $this->debugBar->addCollector(new PhpInfoCollector(), $customKey);
                    } catch (Exception $e) {
                        // Silently ignore if there's still a conflict
                    }
                }
                break;

            default:
                break;
        }
    }

    /**
     * Get collector names
     */
    public function getCollectors(): array
    {
        $debugBar = $this->getDebugBar();
        if (!$debugBar) {
            return [];
        }

        return array_keys($debugBar->getCollectors());
    }

    /**
     * Reset the debug bar
     */
    public function reset(): void
    {
        if ($this->debugBar) {
            $this->debugBar = null;
            $this->renderer = null;
            $this->initialized = false;
        }
    }

    /**
     * Check if debug bar is initialized
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get current memory usage
     */
    public function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'formatted_current' => $this->formatBytes(memory_get_usage(true)),
            'formatted_peak' => $this->formatBytes(memory_get_peak_usage(true)),
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get the base URL for debug bar assets
     */
    public function getBaseUrl(): string
    {
        $renderer = $this->getRenderer();
        if (!$renderer) {
            return '';
        }

        if (method_exists($renderer, 'getBaseUrl')) {
            return $renderer->getBaseUrl();
        }

        $config = $this->configService->getComponentConfig('debug_bar');
        return $config['base_url'] ?? '/debugbar';
    }

    /**
     * Set the base URL for debug bar assets
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $renderer = $this->getRenderer();
        if ($renderer && method_exists($renderer, 'setBaseUrl')) {
            $renderer->setBaseUrl($baseUrl);
        }
    }

    /**
     * Render only the head assets (CSS)
     */
    public function renderHead(): string
    {
        $renderer = $this->getRenderer();
        if (!$renderer) {
            return '';
        }

        return $renderer->renderHead();
    }

    /**
     * Render only the debug bar content (JS and HTML)
     */
    public function renderContent(): string
    {
        $renderer = $this->getRenderer();
        if (!$renderer) {
            return '';
        }

        return $renderer->render();
    }

    /**
     * Inject the debug bar into the response body
     */
    public static function injectDebugBar(MvcEvent $e): void
    {
        $serviceManager = $e->getApplication()->getServiceManager();
        $handler = $serviceManager->get(self::class);

        if (!$handler->shouldDisplay()) {
             // REMOVED: echo "LaminasMicroscope: DebugBar should not display.\n";
             return;
        }

        $response = $e->getResponse();
        $result = $e->getResult();

        if (!$response || !($response instanceof Response)) {
             // REMOVED: echo "LaminasMicroscope: Response is not an HTTP response.\n";
             return;
        }

        // REMOVED: Debug logs before injection logic
        // echo "LaminasMicroscope: Checking response state at FINISH before injection logic.\n";
        // ... rest of debug logs ...
        // echo "--- End FINISH Debug (before check) ---\n";


        // REMOVED: The Content-Type check that was causing issues
        // $contentType = $response->getHeaders()->get('Content-Type');
        // if (!$contentType || strpos($contentType->getFieldValue(), 'text/html') === false) {
        //     // REMOVED: echo "LaminasMicroscope: Content-Type is not text/html. Found: " . ($contentType ? $contentType->getFieldValue() : 'None') . "\n";
        //     return;
        // }

        // Also check if there is content to inject into
        $content = $response->getContent();
        if (empty($content)) {
             // REMOVED: echo "LaminasMicroscope: Response content is empty, cannot inject debug bar.\n";
             return;
        }


        $renderer = $handler->getRenderer();
        if (!$renderer) {
             // REMOVED: echo "LaminasMicroscope: Renderer is null, cannot inject debug bar.\n";
             return;
        }

        $headContent = $renderer->renderHead();
        $bodyContent = $renderer->render();

        $headPos = stripos($content, '</head>');
        $bodyPos = strripos($content, '</body>');

        // REMOVED: Debug logs for head and body tag positions

        // Inject head content before </head>
        if ($headPos !== false) {
            $content = substr($content, 0, $headPos) . $headContent . substr($content, $headPos);
             // Adjust bodyPos if head content was injected before it
             if ($bodyPos !== false && $bodyPos > $headPos) {
                 $bodyPos += strlen($headContent);
             }
             // REMOVED: echo "LaminasMicroscope: Debug bar head injected successfully.\n";
        } else {
             // REMOVED: echo "LaminasMicroscope: </head> tag not found, cannot inject head content.\n";
        }

        // Inject body content before </body>
        if ($bodyPos !== false) {
            $content = substr($content, 0, $bodyPos) . $bodyContent . substr($content, $bodyPos);
            // REMOVED: echo "LaminasMicroscope: Debug bar body injected successfully.\n";
        } else {
             // REMOVED: echo "LaminasMicroscope: </body> tag not found, cannot inject body content.\n";
        }

        $response->setContent($content);
    }

    /**
     * Static method to log response headers and result type at the RENDER event.
     * This helps debug when the Content-Type header is set and what the controller returned.
     */
    public static function logResponseHeadersAndResultAtRender(MvcEvent $e): void
    {
        // REMOVED: All echo statements from this method
    }

    /**
     * Static method to log MvcEvent result type at the DISPATCH event.
     * This helps debug what the controller returned before rendering starts.
     */
    public static function logMvcEventResultAtDispatch(MvcEvent $e): void
    {
        // REMOVED: All echo statements from this method
    }
}
