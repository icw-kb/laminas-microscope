<?php

declare(strict_types=1);

namespace LaminasMicroscope\Exception;

use Exception;

/**
 * Base exception for all Laminas Microscope exceptions
 */
class MicroscopeException extends Exception
{
    /**
     * Create exception with context information
     */
    public static function withContext(string $message, array $context = [], int $code = 0, ?Exception $previous = null): self
    {
        $contextString = empty($context) ? '' : ' Context: ' . json_encode($context);
        return new self($message . $contextString, $code, $previous);
    }
}