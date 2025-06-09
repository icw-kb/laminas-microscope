<?php

declare(strict_types=1);

namespace LaminasMicroscope\Config\Validator\Rule;

/**
 * Validates that field values are within allowed enum values
 */
class EnumValidationRule implements ValidationRule
{
    private array $errors = [];
    private array $enumDefinitions;

    public function __construct(array $enumDefinitions)
    {
        $this->enumDefinitions = $enumDefinitions;
    }

    public function validate(array $config): bool
    {
        $this->errors = [];
        
        foreach ($this->enumDefinitions as $field => $allowedValues) {
            $value = $this->getNestedValue($config, $field);
            
            if ($value !== null && !in_array($value, $allowedValues, true)) {
                $this->errors[] = [
                    'field' => $field,
                    'message' => "Configuration field '{$field}' must be one of: " . implode(', ', $allowedValues) . ". Got: '{$value}'",
                    'type' => 'enum_validation',
                    'allowed' => $allowedValues,
                    'actual' => $value
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
        $keys = explode('.', $key);
        $current = $array;
        
        foreach ($keys as $k) {
            if (!is_array($current) || !array_key_exists($k, $current)) {
                return null;
            }
            $current = $current[$k];
        }
        
        return $current;
    }
}