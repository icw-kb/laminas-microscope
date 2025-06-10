<?php

declare(strict_types=1);

namespace LaminasMicroscope\Exception;

use Exception;

/**
 * Exception thrown when configuration validation fails
 */
class ConfigurationException extends Exception
{
    private array $validationErrors;

    public function __construct(string $message, array $validationErrors = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->validationErrors = $validationErrors;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
