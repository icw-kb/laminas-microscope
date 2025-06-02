<?php

declare(strict_types=1);

namespace LaminasMicroscope\Config;

/**
 * Service for managing Laminas Microscope configuration
 */
class ConfigurationService
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get a configuration value using dot notation
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set a configuration value using dot notation
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$this->config;

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    /**
     * Check if a configuration key exists
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * Get the full configuration as an array
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * Set the entire configuration
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * Merge additional configuration
     */
    public function mergeConfig(array $config): void
    {
        $this->config = array_merge_recursive($this->config, $config);
    }

    /**
     * Get the current environment
     */
    public function getEnvironment(): string
    {
        return (string) $this->get('laminas_microscope.environment', 'development');
    }

    /**
     * Check if Laminas Microscope is enabled
     */
    public function isEnabled(): bool
    {
        return (bool) $this->get('laminas_microscope.enabled', true);
    }

    /**
     * Get the storage path
     */
    public function getStoragePath(): string
    {
        return (string) $this->get('laminas_microscope.storage.path', 'data/laminas-microscope');
    }

    /**
     * Get component configuration
     */
    public function getComponentConfig(string $component): array
    {
        $config = $this->get("laminas_microscope.components.{$component}", []);
        return is_array($config) ? $config : [];
    }

    /**
     * Get data retention period in days
     */
    public function getRetentionDays(): int
    {
        return (int) $this->get('laminas_microscope.storage.retention_days', 30);
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugMode(): bool
    {
        return (bool) $this->get('laminas_microscope.debug_mode', false);
    }

    /**
     * Get maximum file size for uploads/storage
     */
    public function getMaxFileSize(): int
    {
        return (int) $this->get('laminas_microscope.storage.max_file_size', 50 * 1024 * 1024); // 50MB
    }

    /**
     * Get allowed file extensions
     */
    public function getAllowedExtensions(): array
    {
        $extensions = $this->get('laminas_microscope.storage.allowed_extensions', ['json', 'log', 'txt', 'xml']);
        return is_array($extensions) ? $extensions : [];
    }
}
