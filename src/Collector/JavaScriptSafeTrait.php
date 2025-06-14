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
            return $this->cleanDebugOutput($value);
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
        // Handle complex debug formatter output patterns
        
        // 0. Handle JSON-encoded complex structures that contain empty objects or circular refs
        if (strpos($value, '{') !== false && strpos($value, '}') !== false) {
            // Check if this looks like a complex nested structure
            if (strpos($value, '{}') !== false) {
                return '[Object]'; // Contains empty objects - circular reference or empty structure
            }
            
            // Check for deeply nested structures (more than 3 levels)
            $braceDepth = 0;
            $maxDepth = 0;
            for ($i = 0; $i < strlen($value); $i++) {
                if ($value[$i] === '{') {
                    $braceDepth++;
                    $maxDepth = max($maxDepth, $braceDepth);
                } elseif ($value[$i] === '}') {
                    $braceDepth--;
                }
            }
            
            if ($maxDepth >= 2 || strlen($value) > 100) {
                return '[Object]'; // Complex nested structure
            }
        }
        
        // 1. Closure debug output: "Closure() {class: "..." ...}"
        if (preg_match('/^Closure\(\)\s*\{.*\}$/', $value)) {
            return '[Closure]';
        }
        
        // 2. ArrayObject debug output: "ArrayObject {storage: array:..."
        if (preg_match('/^ArrayObject\s*\{.*\}$/', $value)) {
            return '[ArrayObject]';
        }
        
        // 3. DateTime debug output: "DateTime @timestamp {...}"
        if (preg_match('/^DateTime\s*@\d+\s*\{.*\}$/', $value)) {
            return '[DateTime]';
        }
        
        // 4. Generic object debug output: "ClassName {#id ...}"
        if (preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*\s*\{.*\}$/', $value)) {
            // Extract class name
            if (preg_match('/^([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\s*\{/', $value, $matches)) {
                $className = $matches[1];
                // Get just the class name without namespace
                $parts = explode('\\', $className);
                $shortName = end($parts);
                return "[$shortName]";
            }
            return '[Object]';
        }
        
        // 5. Handle DebugBar object references like {#8} (empty objects)
        if (preg_match('/^\{#\d+\}$/', $value)) {
            return '[Object]';
        }
        
        // 6. Remove debug formatter syntax like {#5 +"prop": value}
        $value = preg_replace('/\{#\d+\s*/', '{', $value);
        $value = preg_replace('/\+"([^"]+)"\s*:\s*/', '"$1": ', $value);
        
        // 7. Clean up array debug output with numeric indices
        $value = preg_replace('/array:\d+\s*\[/', '[', $value);
        
        // 8. After cleaning DebugBar syntax, check if the result is complex and should be simplified
        if (strpos($value, '{') !== false && strpos($value, '}') !== false) {
            // Re-check conditions after cleaning DebugBar syntax
            if (strpos($value, '{}') !== false) {
                return '[Object]'; // Contains empty objects after cleaning
            }
            
            // Check for deeply nested structures (more than 3 levels)
            $braceDepth = 0;
            $maxDepth = 0;
            for ($i = 0; $i < strlen($value); $i++) {
                if ($value[$i] === '{') {
                    $braceDepth++;
                    $maxDepth = max($maxDepth, $braceDepth);
                } elseif ($value[$i] === '}') {
                    $braceDepth--;
                }
            }
            
            if ($maxDepth >= 2 || strlen($value) > 100) {
                return '[Object]'; // Complex nested structure after cleaning
            }
        }
        
        // 9. If it still looks like complex debug output, simplify it
        if (strpos($value, '#{') !== false || 
            strpos($value, ' @') !== false ||
            strpos($value, 'array:') !== false ||
            (strpos($value, '{') !== false && strpos($value, 'class:') !== false)) {
            
            // Try to extract simple JSON
            if (preg_match('/\{[^{}]*"[^"]+"\s*:\s*"[^"]*"[^{}]*\}/', $value, $matches)) {
                return $matches[0];
            }
            
            // Fallback: just indicate it's an object
            return '[Object]';
        }
        
        return $value;
    }
}