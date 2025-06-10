<?php

declare(strict_types=1);

namespace LaminasMicroscope\Contracts;

/**
 * Interface for all Microscope component handlers
 */
interface HandlerInterface
{
    /**
     * Check if the handler is enabled
     */
    public function isEnabled(): bool;

    /**
     * Initialize the handler
     */
    public function initialize(): void;

    /**
     * Reset the handler to its initial state
     */
    public function reset(): void;

    /**
     * Check if the handler is initialized
     */
    public function isInitialized(): bool;
}
