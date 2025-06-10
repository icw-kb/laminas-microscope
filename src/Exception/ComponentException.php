<?php

declare(strict_types=1);

namespace LaminasMicroscope\Exception;

/**
 * Exception thrown when component operations fail
 */
class ComponentException extends MicroscopeException
{
    public static function componentNotFound(string $component): self
    {
        return new self("Component not found: {$component}");
    }

    public static function componentNotEnabled(string $component): self
    {
        return new self("Component not enabled: {$component}");
    }

    public static function initializationFailed(string $component, string $reason = ''): self
    {
        $message = "Failed to initialize component: {$component}";
        if ($reason) {
            $message .= " - {$reason}";
        }
        return new self($message);
    }

    public static function dependencyMissing(string $component, string $dependency): self
    {
        return new self("Component '{$component}' is missing required dependency: {$dependency}");
    }
}
