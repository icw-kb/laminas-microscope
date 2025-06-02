<?php

declare(strict_types=1);

namespace LaminasMicroscope\Contracts;

/**
 * Interface for all Laminas Microscope components
 */
interface ComponentInterface
{
    /**
     * Initialize the component
     */
    public function initialize(): void;

    /**
     * Check if the component is enabled
     */
    public function isEnabled(): bool;

    /**
     * Get the component name
     */
    public function getName(): string;

    /**
     * Get the component configuration
     */
    public function getConfig(): array;

    /**
     * Handle events/requests
     */
    public function handle(mixed $event): void;
}
