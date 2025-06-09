<?php

declare(strict_types=1);

namespace LaminasMicroscope\Config\Validator\Rule;

/**
 * Interface for configuration validation rules
 */
interface ValidationRule
{
    /**
     * Validate configuration data
     */
    public function validate(array $config): bool;

    /**
     * Get validation errors
     */
    public function getErrors(): array;
}