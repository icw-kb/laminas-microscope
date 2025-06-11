<?php

declare(strict_types=1);

namespace LaminasMicroscope\Manager;

use Exception;
// Added use statement
use LaminasMicroscope\Collector\CollectorRegistry;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Container\MockContainer;
use LaminasMicroscope\DebugBar\CollectorFactory;
use LaminasMicroscope\DebugBar\DebugBarHandler;
use LaminasMicroscope\Microscope\MicroscopeHandler;
use LaminasMicroscope\Registry;
use LaminasMicroscope\Service\AnalysisService;
use LaminasMicroscope\Whoops\WhoopsHandler;
use Psr\Container\ContainerInterface;

use function array_diff;
use function array_filter;
use function array_keys;
use function array_unique;
use function class_exists;
use function count;
use function error_log;
use function in_array;
use function is_array;
use function memory_get_usage;
use function method_exists;

// Added use statement

/**
 * Manager for all Laminas Microscope components
 */
class ComponentManager
{
    private array $components  = [];
    private array $initialized = [];
    private CollectorRegistry $registry;

    public function __construct(
        private ConfigurationService $configService,
        CollectorRegistry $registry,
        private ?ContainerInterface $container = null
    ) {
        $this->registry = $registry;
        $this->registerComponents();
    }

    /**
     * Get component configuration
     */
    public function getComponentConfig(string $component): array
    {
        return $this->configService->getComponentConfig($component);
    }

    /**
     * Check if a component is enabled (alias for isComponentEnabled for consistency)
     */
    public function isEnabled(string $component): bool
    {
        return $this->isComponentEnabled($component);
    }

    /**
     * Check if a component is enabled
     */
    public function isComponentEnabled(string $component): bool
    {
        $config = $this->getComponentConfig($component);
        return (bool) ($config['enabled'] ?? false);
    }

    /**
     * Get a component instance
     */
    public function getComponent(string $name): ?object
    {
        if (! isset($this->components[$name])) {
            return null;
        }

        if (! isset($this->initialized[$name])) {
            $this->initialized[$name] = $this->createComponent($name);
        }

        return $this->initialized[$name];
    }

    /**
     * Register a component
     */
    public function registerComponent(string $name, string $className): void
    {
        $this->components[$name] = $className;
    }

    /**
     * Get all registered components
     */
    public function getRegisteredComponents(): array
    {
        return array_keys($this->components);
    }

    /**
     * Get all enabled components
     */
    public function getEnabledComponents(): array
    {
        return array_filter(
            $this->getRegisteredComponents(),
            fn($component) => $this->isComponentEnabled($component)
        );
    }

    /**
     * Initialize a specific component
     */
    public function initializeComponent(string $name): ?object
    {
        if (! isset($this->components[$name])) {
            return null;
        }
        $config         = $this->getComponentConfig($name);
        $collectorsOnly = $name === 'debug_bar' && ($config['collectors_only'] ?? false);

        if (! $this->isComponentEnabled($name) && ! $collectorsOnly) {
            return null;
        }

        $component = $this->createComponent($name);
        if ($component === null) {
            return null;
        }

        $this->initialized[$name] = $component;

        // Initialize component if it has initialize method
        if (method_exists($component, 'initialize')) {
            $component->initialize();
        }

        if ($name === 'debug_bar' && class_exists(Registry::class)) {
            Registry::setDebugBar($component);
        }

        return $component;
    }

    /**
     * Initialize all enabled components
     */
    public function initializeAllComponents(): array
    {
        $initialized = [];

        // Get initialization order based on dependencies
        $order = $this->getInitializationOrder();

        foreach ($order as $componentName) {
             // Check if component is still enabled after considering dependencies
            if ($this->isComponentEnabled($componentName)) {
                $component = $this->initializeComponent($componentName);
                if ($component) {
                    $initialized[$componentName] = $component;
                }
            }
        }

        return $initialized;
    }

    /**
     * Check if a component is initialized
     */
    public function isComponentInitialized(string $name): bool
    {
        return isset($this->initialized[$name]);
    }

    /**
     * Reset a component (remove from initialized cache)
     */
    public function resetComponent(string $name): void
    {
        if (isset($this->initialized[$name])) {
            $component = $this->initialized[$name];

            // Call reset method if available
            if (method_exists($component, 'reset')) {
                $component->reset();
            }

            unset($this->initialized[$name]);
        }
    }

    /**
     * Reset all components
     */
    public function resetAllComponents(): void
    {
        foreach (array_keys($this->initialized) as $name) {
            $this->resetComponent($name);
        }
    }

    /**
     * Get component status information
     */
    public function getComponentStatus(): array
    {
        $status = [];

        foreach ($this->getRegisteredComponents() as $name) {
            $status[$name] = [
                'registered'  => true,
                'enabled'     => $this->isComponentEnabled($name),
                'initialized' => $this->isComponentInitialized($name),
                'config'      => $this->getComponentConfig($name),
            ];
        }

        return $status;
    }

    /**
     * Get components by type/category
     */
    public function getComponentsByType(string $type): array
    {
        $components = [];

        foreach ($this->getRegisteredComponents() as $name) {
            $config = $this->getComponentConfig($name);
            if (($config['type'] ?? '') === $type) {
                $components[$name] = $this->getComponent($name);
            }
        }
        return $components;
    }

    /**
     * Check if any components are enabled
     */
    public function hasEnabledComponents(): bool
    {
        return count($this->getEnabledComponents()) > 0;
    }

    /**
     * Get component dependencies
     */
    public function getComponentDependencies(string $name): array
    {
        $config       = $this->configService->getComponentConfig($name);
        $dependencies = $config['dependencies'] ?? [];
        return is_array($dependencies) ? $dependencies : [];
    }

    /**
     * Validate component dependencies
     */
    public function validateDependencies(string $name): array
    {
        $dependencies = $this->getComponentDependencies($name);
        $missing      = [];

        foreach ($dependencies as $dependency) {
            if (! $this->isComponentEnabled($dependency)) {
                $missing[] = $dependency;
            }
        }

        return $missing;
    }

    /**
     * Get initialization order based on dependencies
     */
    public function getInitializationOrder(): array
    {
        $components = $this->getEnabledComponents();
        $ordered    = [];
        $visited    = [];
        $visiting   = []; // To detect circular dependencies

        foreach ($components as $component) {
            if (! in_array($component, $visited)) {
                 $this->sortComponentsByDependencies($component, $ordered, $visited, $visiting);
            }
        }

        return array_unique($ordered);
    }

    /**
     * Enable a component
     */
    public function enableComponent(string $name): bool
    {
        if (! isset($this->components[$name])) {
            return false;
        }

        $this->configService->set("laminas_microscope.components.{$name}.enabled", true);
        return true;
    }

    /**
     * Disable a component
     */
    public function disableComponent(string $name): bool
    {
        if (! isset($this->components[$name])) {
            return false;
        }

        $this->configService->set("laminas_microscope.components.{$name}.enabled", false);
        $this->resetComponent($name);
        return true;
    }

    /**
     * Get component metrics
     */
    public function getComponentMetrics(): array
    {
        return [
            'total_registered'  => count($this->components),
            'total_enabled'     => count($this->getEnabledComponents()),
            'total_initialized' => count($this->initialized),
            'memory_usage'      => memory_get_usage(true),
        ];
    }

    /**
     * Export component configuration
     */
    public function exportConfiguration(): array
    {
        $config = [];

        foreach ($this->getRegisteredComponents() as $name) {
            $config[$name] = $this->getComponentConfig($name);
        }

        return $config;
    }

    /**
     * Import component configuration
     */
    public function importConfiguration(array $config): void
    {
        foreach ($config as $name => $componentConfig) {
            if (isset($this->components[$name])) {
                foreach ($componentConfig as $key => $value) {
                    $this->configService->set("laminas_microscope.components.{$name}.{$key}", $value);
                }
            }
        }
    }

    /**
     * Register default components
     */
    private function registerComponents(): void
    {
        $this->registerComponent('debug_bar', DebugBarHandler::class);
        $this->registerComponent('whoops', WhoopsHandler::class);
        $this->registerComponent('analysis', AnalysisService::class);
        $this->registerComponent('microscope', MicroscopeHandler::class);
    }

    /**
     * Create a component instance
     */
    private function createComponent(string $name): ?object
    {
        if (! isset($this->components[$name])) {
            return null;
        }

        $className = $this->components[$name];

        try {
            // Handle specific component constructors
            switch ($name) {
                case 'microscope':
                    // MicroscopeHandler needs ComponentManager, ConfigurationService, and Container
                    return new $className(
                        $this,
                        $this->configService,
                        $this->container ?? new MockContainer(),
                        $this->registry
                    );

                case 'debug_bar':
                    // DebugBarHandler constructor signature now includes Container and CollectorFactory
                    $collectorFactory = null;
                    if ($this->container && $this->container->has(CollectorFactory::class)) {
                        $collectorFactory = $this->container->get(CollectorFactory::class);
                    } else {
                        // Create a basic CollectorFactory for testing scenarios
                        $collectorFactory = new CollectorFactory($this->container ?? new MockContainer());
                    }
                    return new $className(
                        $this->configService,
                        $this->container ?? new MockContainer(),
                        $this->registry,
                        $collectorFactory
                    ); // Pass container, registry, and collector factory

                case 'whoops':
                    // WhoopsHandler constructor signature
                    return new $className($this->configService);

                case 'analysis':
                    // AnalysisService constructor signature
                    return new $className(
                        $this->configService,
                        $this->getComponent('microscope')
                    );

                default:
                    // Try with ConfigurationService first
                    try {
                        return new $className($this->configService);
                    } catch (Exception $e) {
                        // If that fails, try without parameters
                        return new $className();
                    }
            }
        } catch (Exception $e) {
            // Return null if component creation fails
            error_log("Failed to create component '{$name}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sort components by dependencies (topological sort)
     */
    private function sortComponentsByDependencies(string $component, array &$ordered, array &$visited, array &$visiting): void
    {
        $visiting[] = $component;

        // First, add dependencies
        foreach ($this->getComponentDependencies($component) as $dependency) {
            if ($this->isComponentEnabled($dependency)) {
                if (in_array($dependency, $visiting)) {
                    // Circular dependency detected
                    error_log("Circular dependency detected involving component: {$dependency}");
                    // Handle this error appropriately, maybe skip this dependency or throw exception
                    continue;
                }
                if (! in_array($dependency, $visited)) {
                    $this->sortComponentsByDependencies($dependency, $ordered, $visited, $visiting);
                }
            }
        }

        // Then add the component itself
        $ordered[] = $component;
        $visited[] = $component;
        // Remove from visiting list
        $visiting = array_diff($visiting, [$component]);
    }
}
