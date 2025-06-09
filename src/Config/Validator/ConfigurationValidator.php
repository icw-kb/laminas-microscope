<?php

declare(strict_types=1);

namespace LaminasMicroscope\Config\Validator;

use LaminasMicroscope\Config\Validator\Rule\ValidationRule;

/**
 * Validates Laminas Microscope configuration
 */
class ConfigurationValidator
{
    /** @var ValidationRule[] */
    private array $rules = [];
    
    /** @var string[] */
    private array $errors = [];

    public function addRule(ValidationRule $rule): self
    {
        $this->rules[] = $rule;
        return $this;
    }

    /**
     * Validate configuration array
     */
    public function validate(array $config): bool
    {
        $this->errors = [];
        
        foreach ($this->rules as $rule) {
            if (!$rule->validate($config)) {
                $this->errors = array_merge($this->errors, $rule->getErrors());
            }
        }
        
        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get formatted error messages
     */
    public function getErrorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $error) {
            $messages[] = $error['message'] ?? 'Unknown validation error';
        }
        return $messages;
    }
}