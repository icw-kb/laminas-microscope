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
        // Handle objects with value property (from formatArray methods) FIRST
        if (is_array($data) && isset($data['value']) && count($data) === 1) {
            $cleanValue = $this->cleanDebugOutput($this->ensureString($data['value']));
            return [
                'value' => $cleanValue
            ];
        }
        
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->makeJavaScriptSafe($value);
            }
            return $result;
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
    
    /**
     * Clean up debug formatter output to be JavaScript-safe
     */
    private function cleanDebugOutput(string $value): string
    {
        // Remove debug formatter syntax like {#5 +"prop": value}
        $value = preg_replace('/\{#\d+\s*/', '{', $value);
        $value = preg_replace('/\+"([^"]+)"\s*:\s*/', '"$1": ', $value);
        $value = preg_replace('/DateTime @\d+ \{[^}]+\}/', 'DateTime', $value);
        
        // If it looks like a complex debug output, simplify it
        if (strpos($value, '#{') !== false || strpos($value, ' @') !== false) {
            // Try to extract just the JSON part
            if (preg_match('/\{[^{}]*"[^"]+":[^}]+\}/', $value, $matches)) {
                return $matches[0];
            }
            // Fallback: just indicate it's an object
            return '[Object]';
        }
        
        return $value;
    }
}