<?php

declare(strict_types=1);

namespace LaminasMicroscope\Config\Validator\Rule;

use function array_key_exists;
use function explode;
use function gettype;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;

/**
 * Validates field types in configuration
 */
class TypeValidationRule implements ValidationRule
{
    private array $errors = [];
    private array $typeDefinitions;

    public function __construct(array $typeDefinitions)
    {
        $this->typeDefinitions = $typeDefinitions;
    }

    public function validate(array $config): bool
    {
        $this->errors = [];

        foreach ($this->typeDefinitions as $field => $expectedType) {
            $value = $this->getNestedValue($config, $field);

            if ($value !== null && ! $this->isValidType($value, $expectedType)) {
                $actualType     = gettype($value);
                $this->errors[] = [
                    'field'    => $field,
                    'message'  => "Configuration field '{$field}' must be of type '{$expectedType}', got '{$actualType}'",
                    'type'     => 'type_validation',
                    'expected' => $expectedType,
                    'actual'   => $actualType,
                ];
            }
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get nested value using dot notation
     */
    private function getNestedValue(array $array, string $key): mixed
    {
        $keys    = explode('.', $key);
        $current = $array;

        foreach ($keys as $k) {
            if (! is_array($current) || ! array_key_exists($k, $current)) {
                return null;
            }
            $current = $current[$k];
        }

        return $current;
    }

    /**
     * Check if value matches expected type
     */
    private function isValidType(mixed $value, string $expectedType): bool
    {
        return match ($expectedType) {
            'bool', 'boolean' => is_bool($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value),
            'string' => is_string($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'numeric' => is_numeric($value),
            default => gettype($value) === $expectedType
        };
    }
}
