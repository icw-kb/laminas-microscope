<?php

declare(strict_types=1);

namespace LaminasMicroscope\Exception;

/**
 * Exception thrown when storage operations fail
 */
class StorageException extends MicroscopeException
{
    public static function pathNotWritable(string $path): self
    {
        return new self("Storage path is not writable: {$path}");
    }

    public static function fileNotFound(string $path): self
    {
        return new self("File not found: {$path}");
    }

    public static function writeError(string $path, string $reason = ''): self
    {
        $message = "Failed to write file: {$path}";
        if ($reason) {
            $message .= " - {$reason}";
        }
        return new self($message);
    }

    public static function readError(string $path, string $reason = ''): self
    {
        $message = "Failed to read file: {$path}";
        if ($reason) {
            $message .= " - {$reason}";
        }
        return new self($message);
    }
}