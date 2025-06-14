<?php

declare(strict_types=1);

namespace LaminasMicroscope\Collector;

use function is_object;
use function is_resource;
use function is_array;
use function get_class;
use function json_encode;
use function method_exists;

use const JSON_UNESCAPED_UNICODE;
use const JSON_UNESCAPED_SLASHES;

trait FormatsArrayTrait
{
    /**
     * Format array for proper display in debug bar
     * Converts objects to string representations to avoid [object Object] display
     */
    private function formatArray($data, int $depth = 0): mixed
    {
        // Prevent infinite recursion
        if ($depth > 10) {
            return '[Max depth reached]';
        }

        if (is_object($data)) {
            // Handle special object types
            if ($data instanceof \DateTime || $data instanceof \DateTimeImmutable) {
                return $data->format('Y-m-d H:i:s');
            }
            
            if (method_exists($data, '__toString')) {
                return (string) $data;
            }
            
            if (method_exists($data, 'toArray')) {
                return $this->formatArray($data->toArray(), $depth + 1);
            }
            
            // For VariableListWidget compatibility, wrap objects in value property
            $className = get_class($data);
            
            // Try to serialize the object properties
            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false || $encoded === '{}') {
                return ['value' => 'Object(' . $className . ')'];
            }
            
            return ['value' => 'Object(' . $className . '): ' . $encoded];
        }
        
        if (is_resource($data)) {
            return 'Resource(' . get_resource_type($data) . ')';
        }
        
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->formatArray($value, $depth + 1);
            }
            return $result;
        }
        
        return $data;
    }
}