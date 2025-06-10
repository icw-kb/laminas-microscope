<?php

declare(strict_types=1);

namespace LaminasMicroscope\Exception;

class CacheException extends MicroscopeException
{
    public static function adapterNotFound(string $adapter): self
    {
        return new self("Cache adapter '{$adapter}' not found or not supported");
    }

    public static function connectionFailed(string $adapter, string $details = ''): self
    {
        $message = "Failed to connect to {$adapter} cache";
        if ($details) {
            $message .= ": {$details}";
        }
        return new self($message);
    }

    public static function operationFailed(string $operation, string $reason = ''): self
    {
        $message = "Cache {$operation} operation failed";
        if ($reason) {
            $message .= ": {$reason}";
        }
        return new self($message);
    }
}
