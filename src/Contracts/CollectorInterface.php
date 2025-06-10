<?php

declare(strict_types=1);

namespace LaminasMicroscope\Contracts;

/**
 * Interface for data collectors
 */
interface CollectorInterface
{
    /**
     * Collect and return data
     */
    public function collect(): array;

    /**
     * Get the collector name
     */
    public function getName(): string;
}
