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
        // --- NEW DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::__construct() called. Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END NEW DEBUG LOGGING ---
    }

    /**
     * Check if DebugBar is enabled
     */
    public function isEnabled(): bool
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::isEnabled() called. Instance hash: " . spl_object_hash($this) . ".\n"); // Added log
        // --- END DEBUG LOGGING ---

        $microscopeEnabled = $this->configService->isEnabled(); // Check global enabled status
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::isEnabled() - Global Microscope enabled: " . ($microscopeEnabled ? 'true' : 'false') . ".\n"); // Added log
        // --- END DEBUG LOGGING ---

        if (!$microscopeEnabled) {
            // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: DebugBarHandler::isEnabled() returning false because Microscope is globally disabled.\n"); // Added log
            // --- END DEBUG LOGGING ---
            return false;
        }

        $config = $this->configService->getComponentConfig('debug_bar');
        $componentEnabled = (bool) ($config['enabled'] ?? false);
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::isEnabled() - Debug Bar component enabled in config: " . ($componentEnabled ? 'true' : 'false') . ". Config value: " . ($config['enabled'] ?? 'not set') . ".\n"); // Added log
        // --- END DEBUG LOGGING ---


        $finalEnabled = $componentEnabled; // Debug Bar is enabled if global is enabled AND component is enabled

        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::isEnabled() returning final status: " . ($finalEnabled ? 'true' : 'false') . ".\n"); // Added log
        // --- END DEBUG LOGGING ---

        return $finalEnabled;
    }

    /**
     * Initialize the DebugBar
     */
    public function initialize(): void
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::initialize() called. Instance hash: " . spl_object_hash($this) . ", Initialized: " . ($this->initialized ? 'true' : 'false') . ", Enabled: " . ($this->isEnabled() ? 'true' : 'false') . ".\n");
        // --- END DEBUG LOGGING ---

        $config = $this->configService->getComponentConfig('debug_bar');
        $collectorsOnly = (bool) ($config['collectors_only'] ?? false);

        if ($this->initialized || (!$this->isEnabled() && !$collectorsOnly)) {
            // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: DebugBarHandler::initialize() skipping initialization.\n");
            // --- END DEBUG LOGGING ---
            return;
        }

        $this->debugBar = new StandardDebugBar();
        $this->setupCollectors();

        // --- NEW DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::initialize() - Messages collector present after setup: " . ($this->debugBar->hasCollector('messages') ? 'true' : 'false') . ".\n");
        // --- END NEW DEBUG LOGGING ---


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
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::initialize() finished. Initialized: true.\n");
        // --- END DEBUG LOGGING ---
    }

    /**
     * Get the DebugBar instance
     */
    public function getDebugBar(): ?StandardDebugBar
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::getDebugBar() called. Instance hash: " . spl_object_hash($this) . ", Initialized: " . ($this->initialized ? 'true' : 'false') . ".\n");
        // --- END DEBUG LOGGING ---
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
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::getRenderer() called. Instance hash: " . spl_object_hash($this) . ", Initialized: " . ($this->initialized ? 'true' : 'false') . ".\n");
        // --- END DEBUG LOGGING ---
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
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::addMessage() called with message: \"{$message}\". Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $debugBar = $this->getDebugBar();
        if ($debugBar && $debugBar->hasCollector('messages')) {
            $debugBar->getCollector('messages')->addMessage($message, $label);
            // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: Message added to messages collector.\n");
            // --- END DEBUG LOGGING ---
        } else {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: Message not added. DebugBar or messages collector not available.\n");
            // --- END DEBUG LOGGING ---
        }
    }

    /**
     * Start a timer
     */
    public function startTimer(string $name, ?string $label = null): void
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::startTimer() called for timer: \"{$name}\". Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $debugBar = $this->getDebugBar();
        if ($debugBar && $debugBar->hasCollector('time')) {
            $debugBar->getCollector('time')->startMeasure($name, $label);
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: Timer \"{$name}\" started.\n");
            // --- END DEBUG LOGGING ---
        } else {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: Timer \"{$name}\" not started. DebugBar or time collector not available.\n");
            // --- END DEBUG LOGGING ---
        }
    }

    /**
     * Stop a timer
     */
    public function stopTimer(string $name): void
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::stopTimer() called for timer: \"{$name}\". Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $debugBar = $this->getDebugBar();
        if ($debugBar && $debugBar->hasCollector('time')) {
            $collector = $debugBar->getCollector('time');
            if (method_exists($collector, 'hasStartedMeasure') && $collector->hasStartedMeasure($name)) {
                $collector->stopMeasure($name);
                // --- DEBUG LOGGING ---
                error_log("LaminasMicroscope: DEBUG: Timer \"{$name}\" stopped.\n");
                // --- END DEBUG LOGGING ---
            } else {
                // --- DEBUG LOGGING ---
                error_log("LaminasMicroscope: DEBUG: Timer \"{$name}\" not started; stop skipped.\n");
                // --- END DEBUG LOGGING ---
            }
        } else {
            // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: Timer \"{$name}\" not stopped. DebugBar or time collector not available.\n");
            // --- END DEBUG LOGGING ---
        }
    }

    /**
     * Add a measure (start and end time)
     */
    public function addMeasure(string $label, float $start, float $end): void
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::addMeasure() called for label: \"{$label}\". Instance hash: " . spl_object_hash($this) . ".\n"); // Added log
        // --- END DEBUG LOGGING ---
        $debugBar = $this->getDebugBar();
        if ($debugBar && $debugBar->hasCollector('time')) {
            $debugBar->getCollector('time')->addMeasure($label, $start, $end);
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: Measure \"{$label}\" added.\n"); // Added log
            // --- END DEBUG LOGGING ---
        } else {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: Measure \"{$label}\" not added. DebugBar or time collector not available.\n"); // Added log
            // --- END DEBUG LOGGING ---
        }
    }

    /**
     * Add custom data to a collector
     */
    public function addData(string $collector, string $key, mixed $value): void
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::addData() called for collector: \"{$collector}\", key: \"{$key}\". Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $debugBar = $this->getDebugBar();
        if ($debugBar && $debugBar->hasCollector($collector)) {
            $collectorInstance = $debugBar->getCollector($collector);
            if (method_exists($collectorInstance, 'addData')) {
                $collectorInstance->addData($key, $value);
                 // --- DEBUG LOGGING ---
                error_log("LaminasMicroscope: DEBUG: Data added to collector \"{$collector}\".\n");
                // --- END DEBUG LOGGING ---
            } else {
                 // --- DEBUG LOGGING ---
                error_log("LaminasMicroscope: DEBUG: Data not added. Collector \"{$collector}\" does not have addData method.\n");
                // --- END DEBUG LOGGING ---
            }
        } else {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: Data not added. DebugBar or collector \"{$collector}\" not available.\n");
            // --- END DEBUG LOGGING ---
        }
    }

    /**
     * Get collected data
     */
    public function getData(): array
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::getData() called. Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $debugBar = $this->getDebugBar();
        if (!$debugBar) {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: getData() returning empty array because DebugBar is null.\n");
            // --- END DEBUG LOGGING ---
            return [];
        }

        return $debugBar->getData();
    }

    /**
     * Render the debug bar HTML
     */
    public function renderHtml(): string
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::renderHtml() called. Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $renderer = $this->getRenderer();
        if (!$renderer) {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: renderHtml() returning empty string because renderer is null.\n");
            // --- END DEBUG LOGGING ---
            return '';
        }

        $html = $renderer->renderHead() . $renderer->render();
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: renderHtml() finished. Output length: " . strlen($html) . ".\n");
        // --- END DEBUG LOGGING ---
        return $html;
    }

    /**
     * Get the debug bar assets (CSS/JS)
     */
    public function getAssets(): array
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::getAssets() called. Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $renderer = $this->getRenderer();
        if (!$renderer) {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: getAssets() returning empty array because renderer is null.\n");
            // --- END DEBUG LOGGING ---
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
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: getAssets() finished. Found CSS: " . count($assets['css']) . ", JS: " . count($assets['js']) . ".\n");
            // --- END DEBUG LOGGING ---

        } catch (Exception $e) {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: ERROR: getAssets() failed: " . $e->getMessage() . ".\n");
            // --- END DEBUG LOGGING ---
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
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::shouldDisplay() called. Instance hash: " . spl_object_hash($this) . ".\n"); // Corrected newline
        // --- END DEBUG LOGGING ---

        $config = $this->configService->getComponentConfig('debug_bar');
        if (($config['collectors_only'] ?? false)) {
            return false;
        }

        if (!$this->isEnabled()) {
            // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: shouldDisplay() returning false because DebugBar is not enabled.\n"); // Corrected newline
            // --- END DEBUG LOGGING ---
            return false;
        }

        $environment = $this->configService->getEnvironment();

        if ($environment === 'production') {
            $showInProduction = (bool) ($config['show_in_production'] ?? false);
            // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: shouldDisplay() in production. show_in_production: " . ($showInProduction ? 'true' : 'false') . ". Returning " . ($showInProduction ? 'true' : 'false') . ".\n"); // Corrected newline
            // --- END DEBUG LOGGING ---
            return $showInProduction;
        }

        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: shouldDisplay() in non-production environment ('" . $environment . "'). Returning true.\n"); // Corrected newline
        // --- END DEBUG LOGGING ---
        return true;
    }

    /**
     * Setup default collectors
     */
    private function setupCollectors(): void
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::setupCollectors() called. Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        if (!$this->debugBar) {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: setupCollectors() skipping because DebugBar is null.\n");
            // --- END DEBUG LOGGING ---
            return;
        }

        $config = $this->configService->getComponentConfig('debug_bar');
        $collectors = $config['collectors'] ?? ['time', 'memory', 'messages'];

        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: Configured collectors: " . implode(', ', $collectors) . ".\n");
        // --- END DEBUG LOGGING ---

        foreach ($collectors as $collectorName) {
            $this->addCollector($collectorName);
        }
         // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: setupCollectors() finished.\n");
        // --- END DEBUG LOGGING ---
    }

    /**
     * Add a specific collector
     */
    private function addCollector(string $name): void
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::addCollector() called for: \"{$name}\". Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        if (!$this->debugBar) {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: addCollector() skipping because DebugBar is null.\n");
            // --- END DEBUG LOGGING ---
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

        if (!$mapping) {
            error_log("LaminasMicroscope: DEBUG: Unknown collector requested: \"{$name}\". Skipping.\n");
            return;
        }

        try {
            if ($this->container->has($mapping)) {
                $collector = $this->container->get($mapping);
            } elseif (class_exists($mapping)) {
                $collector = new $mapping();
            } else {
                error_log("LaminasMicroscope: DEBUG: Unknown collector mapping: \"{$mapping}\". Skipping.\n");
                return;
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
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::getCollectors() called. Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $debugBar = $this->getDebugBar();
        if (!$debugBar) {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: getCollectors() returning empty array because DebugBar is null.\n");
            // --- END DEBUG LOGGING ---
            return [];
        }
        $collectors = array_keys($debugBar->getCollectors());
         // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: getCollectors() found: " . implode(', ', $collectors) . ".\n");
        // --- END DEBUG LOGGING ---
        return $collectors;
    }

    /**
     * Reset the debug bar
     */
    public function reset(): void
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::reset() called. Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        if ($this->debugBar) {
            $this->debugBar = null;
            $this->renderer = null;
            $this->initialized = false;
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: DebugBarHandler reset finished.\n");
            // --- END DEBUG LOGGING ---
        } else {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: DebugBarHandler reset skipped, not initialized.\n");
            // --- END DEBUG LOGGING ---
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
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::getMemoryUsage() called. Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $usage = [
            'current' => $current,
            'peak' => $peak,
            'formatted_current' => $this->formatBytes($current),
            'formatted_peak' => $this->formatBytes($peak),
        ];
         // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: getMemoryUsage() returning current: " . $usage['formatted_current'] . ", peak: " . $usage['formatted_peak'] . ".\n");
        // --- END DEBUG LOGGING ---
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
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::getBaseUrl() called. Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $renderer = $this->getRenderer();
        if (!$renderer) {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: getBaseUrl() returning empty string because renderer is null.\n");
            // --- END DEBUG LOGGING ---
            return '';
        }

        if (method_exists($renderer, 'getBaseUrl')) {
             $baseUrl = $renderer->getBaseUrl();
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: getBaseUrl() returning from renderer: \"{$baseUrl}\".\n");
            // --- END DEBUG LOGGING ---
            return $baseUrl;
        }

        $config = $this->configService->getComponentConfig('debug_bar');
        $baseUrl = $config['base_url'] ?? '/_debug/debugbar/resources'; // Default to the asset route
         // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: getBaseUrl() returning from config: \"{$baseUrl}\".\n");
        // --- END DEBUG LOGGING ---
        return $baseUrl;
    }

    /**
     * Set the base URL for debug bar assets
     */
    public function setBaseUrl(string $baseUrl): void
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::setBaseUrl() called with: \"{$baseUrl}\". Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $renderer = $this->getRenderer();
        if ($renderer && method_exists($renderer, 'setBaseUrl')) {
            $renderer->setBaseUrl($baseUrl);
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: setBaseUrl() set on renderer.\n");
            // --- END DEBUG LOGGING ---
        } else {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: setBaseUrl() not set on renderer. Renderer null or method missing.\n");
            // --- END DEBUG LOGGING ---
        }
    }

    /**
     * Render only the head assets (CSS)
     */
    public function renderHead(): string
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::renderHead() called. Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $renderer = $this->getRenderer();
        if (!$renderer) {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: renderHead() returning empty string because renderer is null.\n");
            // --- END DEBUG LOGGING ---
            return '';
        }

        $head = $renderer->renderHead();
         // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: renderHead() finished. Output length: " . strlen($head) . ".\n");
        // --- END DEBUG LOGGING ---
        return $head;
    }

    /**
     * Render only the debug bar content (JS and HTML)
     */
    public function renderContent(): string
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::renderContent() called. Instance hash: " . spl_object_hash($this) . ".\n");
        // --- END DEBUG LOGGING ---
        $renderer = $this->getRenderer();
        if (!$renderer) {
             // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: renderContent() returning empty string because renderer is null.\n");
            // --- END DEBUG LOGGING ---
            return '';
        }

        $content = $renderer->render();
         // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: renderContent() finished. Output length: " . strlen($content) . ".\n");
        // --- END DEBUG LOGGING ---
        return $content;
    }

    /**
     * Inject the debug bar into the response body
     */
    public static function injectDebugBar(MvcEvent $e): void
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: DebugBarHandler::injectDebugBar() called.\n");
        // --- END DEBUG LOGGING ---

        $serviceManager = $e->getApplication()->getServiceManager();
        $handler = $serviceManager->get(self::class);

        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: injectDebugBar() - Checking shouldDisplay(). Instance hash: " . spl_object_hash($handler) . ".\n");
        // --- END DEBUG LOGGING ---
        if (!$handler->shouldDisplay()) {
             // --- DEBUG LOGGING ---
             error_log("LaminasMicroscope: DEBUG: injectDebugBar() - shouldDisplay() is false. Aborting injection.\n");
             // --- END DEBUG LOGGING ---
             return;
        }

        $response = $e->getResponse();
        $result = $e->getResult();

        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: injectDebugBar() - Checking response type.\n");
        // --- END DEBUG LOGGING ---
        if (!$response || !($response instanceof Response)) {
             // --- DEBUG LOGGING ---
             error_log("LaminasMicroscope: DEBUG: injectDebugBar() - Response is not an HTTP response. Aborting injection.\n");
             // --- END DEBUG LOGGING ---
             return;
        }

        // --- DEBUG LOGGING ---
        // REMOVED: Content-Type header check to allow injection even if header is missing/incorrect
        error_log("LaminasMicroscope: DEBUG: injectDebugBar() - Checking Content-Type header.\n");
        $contentType = $response->getHeaders()->get('Content-Type');
        error_log("LaminasMicroscope: DEBUG: injectDebugBar() - Content-Type is: " . ($contentType ? $contentType->getFieldValue() : 'None') . ".\n");
        // if (!$contentType || strpos($contentType->getFieldValue(), 'text/html') === false) {
        //      error_log("LaminasMicroscope: DEBUG: injectDebugBar() - Content-Type is not text/html. Found: " . ($contentType ? $contentType->getFieldValue() : 'None') . ".\n");
        //      return;
        // }
        // --- END DEBUG LOGGING ---

        // Also check if there is content to inject into
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: injectDebugBar() - Checking response content.\n");
        // --- END DEBUG LOGGING ---
        $content = $response->getContent();
        if (empty($content)) {
             // --- DEBUG LOGGING ---
             error_log("LaminasMicroscope: DEBUG: injectDebugBar() - Response content is empty, cannot inject debug bar. Aborting injection.\n");
             // --- END DEBUG LOGGING ---
             return;
        }

        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: injectDebugBar() - Getting renderer.\n");
        // --- END DEBUG LOGGING ---
        $renderer = $handler->getRenderer();
        if (!$renderer) {
             // --- DEBUG LOGGING ---
             error_log("LaminasMicroscope: DEBUG: injectDebugBar() - Renderer is null, cannot inject debug bar. Aborting injection.\n");
             // --- END DEBUG LOGGING ---
             return;
        }

        $headContent = $renderer->renderHead();
        $bodyContent = $renderer->render();

        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: injectDebugBar() - Searching for </head> and </body> tags.\n");
        // --- END DEBUG LOGGING ---
        $headPos = stripos($content, '</head>');
        $bodyPos = strripos($content, '</body>');

        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: injectDebugBar() - Found </head> at: " . ($headPos === false ? 'false' : $headPos) . ", </body> at: " . ($bodyPos === false ? 'false' : $bodyPos) . ".\n");
        // --- END DEBUG LOGGING ---

        // Inject head content before </head>
        if ($headPos !== false) {
            $content = substr($content, 0, $headPos) . $headContent . substr($content, $headPos);
             // Adjust bodyPos if head content was injected before it
             if ($bodyPos !== false && $bodyPos > $headPos) {
                 $bodyPos += strlen($headContent);
             }
             // --- DEBUG LOGGING ---
             error_log("LaminasMicroscope: DEBUG: Debug bar head injected successfully.\n");
             // --- END DEBUG LOGGING ---
        } else {
             // --- DEBUG LOGGING ---
             error_log("LaminasMicroscope: DEBUG: </head> tag not found, cannot inject head content.\n");
             // --- END DEBUG LOGGING ---
        }

        // Inject body content before </body>
        if ($bodyPos !== false) {
            $content = substr($content, 0, $bodyPos) . $bodyContent . substr($content, $bodyPos);
            // --- DEBUG LOGGING ---
            error_log("LaminasMicroscope: DEBUG: Debug bar body injected successfully.\n");
            // --- END DEBUG LOGGING ---
        } else {
             // --- DEBUG LOGGING ---
             error_log("LaminasMicroscope: DEBUG: </body> tag not found, cannot inject body content.\n");
             // --- END DEBUG LOGGING ---
        }

        $response->setContent($content);

        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: injectDebugBar() finished setting response content.\n");
        // --- END DEBUG LOGGING ---
    }

    /**
     * Static method to log response headers and result type at the RENDER event.
     * This helps debug when the Content-Type header is set and what the controller returned.
     */
    public static function logResponseHeadersAndResultAtRender(MvcEvent $e): void
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: logResponseHeadersAndResultAtRender() triggered.\n"); // Corrected newline
        // --- END DEBUG LOGGING ---
        $response = $e->getResponse();
        if ($response) {
            // \n is correct PHP syntax for a newline in a double-quoted string
            $contentType = $response->getHeaders()->get('Content-Type');
            error_log("LaminasMicroscope: DEBUG: RENDER event - Response Content-Type: " . ($contentType ? $contentType->getFieldValue() : 'None') . ".\n"); // Corrected newline
        }
        $result = $e->getResult();
        // \n is correct PHP syntax for a newline in a double-quoted string
        error_log("LaminasMicroscope: DEBUG: RENDER event - MvcEvent Result Type: " . (is_object($result) ? get_class($result) : gettype($result)) . ".\n"); // Corrected newline
        // --- END DEBUG LOGGING ---
    }

    /**
     * Static method to log MvcEvent result type at the DISPATCH event.
     * This helps debug what the controller returned before rendering starts.
     */
    public static function logMvcEventResultAtDispatch(MvcEvent $e): void
    {
        // --- DEBUG LOGGING ---
        error_log("LaminasMicroscope: DEBUG: logMvcEventResultAtDispatch() triggered.\n"); // Corrected newline
        // --- END DEBUG LOGGING ---
        $result = $e->getResult();
        // \n is correct PHP syntax for a newline in a double-quoted string
        error_log("LaminasMicroscope: DEBUG: DISPATCH event - MvcEvent Result Type: " . (is_object($result) ? get_class($result) : gettype($result)) . ".\n"); // Corrected newline
        // --- END DEBUG LOGGING ---
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
