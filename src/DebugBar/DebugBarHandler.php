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
use LaminasMicroscope\DebugBar\Collectors\PDOCollector;
use LaminasMicroscope\DebugBar\Collectors\LaminasRequestCollector;
use LaminasMicroscope\DebugBar\Collectors\LaminasConfigCollector;
use LaminasMicroscope\Collector\CollectorRegistry;

/**
 * Handler for DebugBar integration
 */
class DebugBarHandler
{
    private ?StandardDebugBar $debugBar = null;
    private bool $initialized = false;
    private ContainerInterface $container;
    private CollectorRegistry $collectorRegistry;
    private ?JavascriptRenderer $renderer = null;
    private array $collectorMap = [
        'time' => TimeDataCollector::class,
        'memory' => MemoryCollector::class,
        'messages' => MessagesCollector::class,
        'phpinfo' => PhpInfoCollector::class,
        'pdo' => PDOCollector::class,
        'request' => LaminasRequestCollector::class,
        'config' => LaminasConfigCollector::class,
    ];

    public function __construct(
        private ConfigurationService $configService,
        ContainerInterface $container,
        CollectorRegistry $collectorRegistry
    ) {
        $this->container = $container;
        $this->collectorRegistry = $collectorRegistry;
        $config = $this->configService->getComponentConfig('debug_bar');
        if (isset($config['collector_map']) && is_array($config['collector_map'])) {
            $this->collectorMap = array_merge($this->collectorMap, $config['collector_map']);
        }
    }

    /**
     * Check if DebugBar is enabled
     */
    public function isEnabled(): bool
    {

        $microscopeEnabled = $this->configService->isEnabled(); // Check global enabled status

        if (!$microscopeEnabled) {
            return false;
        }

        $config = $this->configService->getComponentConfig('debug_bar');
        $componentEnabled = (bool) ($config['enabled'] ?? false);


        $finalEnabled = $componentEnabled; // Debug Bar is enabled if global is enabled AND component is enabled


        return $finalEnabled;
    }

    /**
     * Initialize the DebugBar
     */
    public function initialize(): void
    {

        $config = $this->configService->getComponentConfig('debug_bar');
        $collectorsOnly = (bool) ($config['collectors_only'] ?? false);

        if ($this->initialized || (!$this->isEnabled() && !$collectorsOnly)) {
            return;
        }

        $this->debugBar = new StandardDebugBar();
        $this->setupCollectors();



        if (!$collectorsOnly) {
            $this->renderer = $this->debugBar->getJavascriptRenderer();
            $baseUrl = $config['base_url'] ?? '/_debug/debugbar/resources';
            if ($this->renderer && method_exists($this->renderer, 'setBaseUrl')) {
                $this->renderer->setBaseUrl($baseUrl);
            }
        }

        $this->initialized = true;
        if (class_exists(\LaminasMicroscope\Registry::class)) {
            \LaminasMicroscope\Registry::setDebugBar($this);
        }
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
        } else {
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
        } else {
        }
    }

    /**
     * Stop a timer
     */
    public function stopTimer(string $name): void
    {
        $debugBar = $this->getDebugBar();
        if ($debugBar && $debugBar->hasCollector('time')) {
            $collector = $debugBar->getCollector('time');
            if (method_exists($collector, 'hasStartedMeasure') && $collector->hasStartedMeasure($name)) {
                $collector->stopMeasure($name);
            } else {
            }
        } else {
        }
    }

    /**
     * Add a measure (start and end time)
     */
    public function addMeasure(string $label, float $start, float $end): void
    {
        $debugBar = $this->getDebugBar();
        if ($debugBar && $debugBar->hasCollector('time')) {
            $debugBar->getCollector('time')->addMeasure($label, $start, $end);
        } else {
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
            } else {
            }
        } else {
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

        $html = $renderer->renderHead() . $renderer->render();
        return $html;
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

        $config = $this->configService->getComponentConfig('debug_bar');
        if (($config['collectors_only'] ?? false)) {
            return false;
        }

        if (!$this->isEnabled()) {
            return false;
        }

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
        $existing = $this->collectorRegistry->get($name);
        if ($existing && !$this->debugBar->hasCollector($existing->getName())) {
            $this->debugBar->addCollector($existing);
            return;
        }

        $mapping = $this->collectorMap[$name] ?? null;

        if ($this->debugBar->hasCollector($name)) {
            $collector = $this->debugBar->getCollector($name);
            $this->collectorRegistry->register($collector);
            return;
        }

        if (!$mapping) {            return;
        }

        try {
            if ($this->container->has($mapping)) {
                $collector = $this->container->get($mapping);
            } elseif (class_exists($mapping)) {
                $collector = new $mapping();
            } else {                return;
            }

            $collectorName = method_exists($collector, 'getName') ? $collector->getName() : $name;
            if (!$this->debugBar->hasCollector($collectorName)) {
                $this->debugBar->addCollector($collector, $collectorName);
            }
            $this->collectorRegistry->register($collector);
        } catch (Exception $e) {
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
        $collectors = array_keys($debugBar->getCollectors());
        return $collectors;
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
        } else {
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
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $usage = [
            'current' => $current,
            'peak' => $peak,
            'formatted_current' => $this->formatBytes($current),
            'formatted_peak' => $this->formatBytes($peak),
        ];
        return $usage;
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $size Size in bytes
     * @param int $precision Number of decimal places
     * @return string Formatted size string
     */
    private function formatBytes($size, $precision = 2): string
    {
        if ($size === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($size, 0); // Use $size here, not $bytes
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
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
             $baseUrl = $renderer->getBaseUrl();
            return $baseUrl;
        }

        $config = $this->configService->getComponentConfig('debug_bar');
        $baseUrl = $config['base_url'] ?? '/_debug/debugbar/resources'; // Default to the asset route
        return $baseUrl;
    }

    /**
     * Set the base URL for debug bar assets
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $renderer = $this->getRenderer();
        if ($renderer && method_exists($renderer, 'setBaseUrl')) {
            $renderer->setBaseUrl($baseUrl);
        } else {
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

        $head = $renderer->renderHead();
        return $head;
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

        $content = $renderer->render();
        return $content;
    }

    /**
     * Inject the debug bar into the response body
     */
    public static function injectDebugBar(MvcEvent $e): void
    {
        $serviceManager = $e->getApplication()->getServiceManager();
        $handler = $serviceManager->get(self::class);

        if (!$handler->shouldDisplay()) {
            return;
        }

        $response = $e->getResponse();
        if (!$response instanceof Response) {
            return;
        }

        $content = $response->getContent();
        if (!is_string($content) || $content === '') {
            return;
        }

        $renderer = $handler->getRenderer();
        if (!$renderer) {
            return;
        }

        $headContent = $renderer->renderHead();
        $bodyContent = $renderer->render();

        $headPos = stripos($content, '</head>');
        $bodyPos = strripos($content, '</body>');

        if ($headPos !== false) {
            $content = substr($content, 0, $headPos) . $headContent . substr($content, $headPos);
            if ($bodyPos !== false && $bodyPos > $headPos) {
                $bodyPos += strlen($headContent);
            }
        } else {
            $content = $headContent . $content;
            if ($bodyPos !== false) {
                $bodyPos += strlen($headContent);
            }
        }

        if ($bodyPos !== false) {
            $content = substr($content, 0, $bodyPos) . $bodyContent . substr($content, $bodyPos);
        } else {
            $content .= $bodyContent;
        }

        $response->setContent($content);
    }

    /**
     * Static method to log response headers and result type at the RENDER event.
     * This helps debug when the Content-Type header is set and what the controller returned.
     */
    public static function logResponseHeadersAndResultAtRender(MvcEvent $e): void
    {
        $response = $e->getResponse();
        if ($response) {
            // \n is correct PHP syntax for a newline in a double-quoted string
            $contentType = $response->getHeaders()->get('Content-Type');
        }
        $result = $e->getResult();
        // \n is correct PHP syntax for a newline in a double-quoted string
    }

    /**
     * Static method to log MvcEvent result type at the DISPATCH event.
     * This helps debug what the controller returned before rendering starts.
     */
    public static function logMvcEventResultAtDispatch(MvcEvent $e): void
    {
        $result = $e->getResult();
        // \n is correct PHP syntax for a newline in a double-quoted string
    }

    /**
     * Format duration in seconds to a human-readable string (ms or µs)
     *
     * @param float $seconds Duration in seconds
     * @return string Formatted duration string
     */
    public function formatDuration(float $seconds): string
    {
        if ($seconds < 0.001) {
            return round($seconds * 1000000) . 'µs';
        } elseif ($seconds < 1) {
            return round($seconds * 1000, 2) . 'ms';
        }

        return round($seconds, 2) . 's';
    }

    /**
     * Normalize SQL query for duplicate detection
     */
    private function normalizeSql(string $sql): string
    {
        // Remove parameters and normalize whitespace for duplicate detection
        // Correct regex escaping:
        // \? matches a literal question mark
        // \$\d+ matches a dollar sign followed by one or more digits ($1, $2, etc.)
        // :\w+ matches a colon followed by one or more word characters (:param, :name, etc.)
        // These need \\ in the PHP string to become \ in the regex engine
        $sql = preg_replace('/\\?|\\$\\d+|:\\w+/', '?', $sql);
        // \s+ matches one or more whitespace characters
        // This needs \\ in the PHP string to become \ in the regex engine
        $sql = preg_replace('/\\s+/', ' ', $sql);
        return trim(strtolower($sql));
    }
}
