<?php

declare(strict_types=1);

namespace LaminasMicroscope\Config\Validator\Rule;

/**
 * Validates that required fields are present in configuration
 */
class RequiredFieldRule implements ValidationRule
{
    private array $errors = [];
    private array $requiredFields;

    public function __construct(array $requiredFields)
    {
        $this->requiredFields = $requiredFields;
    }

    public function validate(array $config): bool
    {
        $this->errors = [];
        
        foreach ($this->requiredFields as $field) {
            if (!$this->hasNestedKey($config, $field)) {
                $this->errors[] = [
                    'field' => $field,
                    'message' => "Required configuration field '{$field}' is missing",
                    'type' => 'required_field'
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
     * Check if nested key exists using dot notation
     */
    private function hasNestedKey(array $array, string $key): bool
    {
        $keys = explode('.', $key);
        $current = $array;
        
        foreach ($keys as $k) {
            if (!is_array($current) || !array_key_exists($k, $current)) {
                return false;
            }
            $current = $current[$k];
        }
        
        return true;
    }
}