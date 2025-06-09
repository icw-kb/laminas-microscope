<?php

declare(strict_types=1);

namespace LaminasMicroscope\Config\Validator;

use LaminasMicroscope\Config\Validator\Rule\RequiredFieldRule;
use LaminasMicroscope\Config\Validator\Rule\TypeValidationRule;
use LaminasMicroscope\Config\Validator\Rule\EnumValidationRule;

/**
 * Factory for creating a pre-configured ConfigurationValidator
 */
class ConfigurationValidatorFactory
{
    public static function create(): ConfigurationValidator
    {
        $validator = new ConfigurationValidator();
        
        // Required fields validation
        $validator->addRule(new RequiredFieldRule([
            'laminas_microscope.enabled',
        ]));
        
        // Type validation
        $validator->addRule(new TypeValidationRule([
            'laminas_microscope.enabled' => 'boolean',
            'laminas_microscope.environment' => 'string',
            'laminas_microscope.storage.path' => 'string',
            'laminas_microscope.storage.retention_days' => 'integer',
            'laminas_microscope.components.whoops.enabled' => 'boolean',
            'laminas_microscope.components.whoops.show_in_production' => 'boolean',
            'laminas_microscope.components.debug_bar.enabled' => 'boolean',
            'laminas_microscope.components.debug_bar.collectors_only' => 'boolean',
            'laminas_microscope.components.debug_bar.show_in_production' => 'boolean',
            'laminas_microscope.components.debug_bar.collectors' => 'array',
            'laminas_microscope.components.microscope.enabled' => 'boolean',
            'laminas_microscope.components.microscope.auto_analyze' => 'boolean',
        ]));
        
        // Enum validation
        $validator->addRule(new EnumValidationRule([
            'laminas_microscope.environment' => ['development', 'testing', 'staging', 'production'],
        ]));
        
        return $validator;
    }
}