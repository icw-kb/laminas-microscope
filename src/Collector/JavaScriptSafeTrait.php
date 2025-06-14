<?php

declare(strict_types=1);

namespace LaminasMicroscope\Collector;

trait JavaScriptSafeTrait
{
    /**
     * Ensure data is completely safe for JavaScript consumption
     * This prevents text.replace errors by ensuring all values are properly typed
     */
    private function makeJavaScriptSafe($data): mixed
    {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->makeJavaScriptSafe($value);
            }
            return $result;
        }
        
        // Handle objects with value property (from formatArray methods)
        if (is_array($data) && isset($data['value'])) {
            return [
                'value' => $this->ensureString($data['value'])
            ];
        }
        
        // For scalar values, ensure they're properly typed
        if (is_null($data)) {
            return null;
        }
        
        if (is_bool($data)) {
            return $data;
        }
        
        if (is_numeric($data)) {
            return $data;
        }
        
        // Everything else should be a string
        return $this->ensureString($data);
    }
    
    /**
     * Ensure a value is converted to a safe string
     */
    private function ensureString($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        
        if (is_null($value)) {
            return '';
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_numeric($value)) {
            return (string) $value;
        }
        
        if (is_array($value)) {
            return json_encode($value) ?: '[Array]';
        }
        
        if (is_object($value)) {
            return json_encode($value) ?: '[Object]';
        }
        
        // Fallback for any other type
        return '[' . gettype($value) . ']';
    }
}