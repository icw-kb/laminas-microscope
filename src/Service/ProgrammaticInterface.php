<?php

declare(strict_types=1);

namespace LaminasMicroscope\Service;

use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Manager\ComponentManager;

use function in_array;

/**
 * Programmatic interface for controlling debug suite components
 */
class ProgrammaticInterface
{
    private ComponentManager $componentManager;
    private ConfigurationService $config;

    public function __construct(ComponentManager $componentManager, ConfigurationService $config)
    {
        $this->componentManager = $componentManager;
        $this->config           = $config;
    }

    /**
     * Enable debug suite globally
     */
    public function enableAll(): self
    {
        $this->config->set('laminas_debug_suite.enabled', true);
        return $this;
    }

    /**
     * Disable debug suite globally
     */
    public function disableAll(): self
    {
        $this->config->set('laminas_debug_suite.enabled', false);
        return $this;
    }

    /**
     * Enable specific component
     */
    public function enableComponent(string $component): self
    {
        $this->componentManager->enable($component);
        return $this;
    }

    /**
     * Disable specific component
     */
    public function disableComponent(string $component): self
    {
        $this->componentManager->disable($component);
        return $this;
    }

    /**
     * Toggle component state
     */
    public function toggleComponent(string $component): self
    {
        $this->componentManager->toggle($component);
        return $this;
    }

    /**
     * Enable only specified components (disable others)
     */
    public function enableOnly(array $components): self
    {
        $allComponents = $this->componentManager->getAvailableComponents();

        foreach ($allComponents as $component) {
            if (in_array($component, $components)) {
                $this->componentManager->enable($component);
            } else {
                $this->componentManager->disable($component);
            }
        }

        return $this;
    }

    /**
     * Create a profile configuration for different environments
     */
    public function createProfile(string $name, array $config): self
    {
        $this->config->set("profiles.{$name}", $config);
        return $this;
    }

    /**
     * Load a configuration profile
     */
    public function loadProfile(string $name): self
    {
        $profile = $this->config->get("profiles.{$name}");

        if ($profile) {
            foreach ($profile as $key => $value) {
                $this->config->set("laminas_debug_suite.{$key}", $value);
            }
        }

        return $this;
    }

    /**
     * Get current component status
     */
    public function getStatus(): array
    {
        $components = $this->componentManager->getAvailableComponents();
        $status     = [];

        foreach ($components as $component) {
            $status[$component] = $this->componentManager->isEnabled($component);
        }

        return [
            'global_enabled'    => $this->config->get('laminas_debug_suite.enabled'),
            'environment'       => $this->config->get('laminas_debug_suite.environment'),
            'components'        => $status,
            'runtime_overrides' => $this->componentManager->getRuntimeOverrides(),
        ];
    }

    /**
     * Reset all runtime overrides
     */
    public function resetToDefaults(): self
    {
        $this->componentManager->clearRuntimeOverrides();
        return $this;
    }
}
