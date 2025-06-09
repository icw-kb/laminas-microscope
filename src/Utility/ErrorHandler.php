<?php

declare(strict_types=1);

namespace LaminasMicroscope\Utility;

use LaminasMicroscope\Exception\MicroscopeException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Centralized error handling utility for Laminas Microscope
 */
class ErrorHandler
{
    private static ?LoggerInterface $logger = null;
    private static bool $debugMode = false;

    public static function setLogger(?LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    public static function setDebugMode(bool $debugMode): void
    {
        self::$debugMode = $debugMode;
    }

    /**
     * Handle an exception with proper logging and optional re-throwing
     */
    public static function handle(Throwable $exception, string $context = '', bool $rethrow = false): void
    {
        $message = self::formatExceptionMessage($exception, $context);
        
        // Log the error
        if (self::$logger) {
            self::$logger->error($message, [
                'exception' => $exception,
                'context' => $context,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => self::$debugMode ? $exception->getTraceAsString() : null
            ]);
        } else {
            // Fallback to error_log if no logger available
            error_log("LaminasMicroscope Error: {$message}");
            if (self::$debugMode) {
                error_log("Stack trace: " . $exception->getTraceAsString());
            }
        }

        if ($rethrow) {
            throw $exception;
        }
    }

    /**
     * Handle an exception safely without throwing, returns success status
     */
    public static function handleSafely(callable $operation, string $context = ''): bool
    {
        try {
            $operation();
            return true;
        } catch (Throwable $e) {
            self::handle($e, $context, false);
            return false;
        }
    }

    /**
     * Execute operation with error handling and default return value
     */
    public static function executeWithDefault(callable $operation, mixed $default = null, string $context = ''): mixed
    {
        try {
            return $operation();
        } catch (Throwable $e) {
            self::handle($e, $context, false);
            return $default;
        }
    }

    /**
     * Create a MicroscopeException with proper context
     */
    public static function createException(string $message, array $context = [], ?Throwable $previous = null): MicroscopeException
    {
        return MicroscopeException::withContext($message, $context, 0, $previous);
    }

    /**
     * Format exception message with context
     */
    private static function formatExceptionMessage(Throwable $exception, string $context): string
    {
        $message = get_class($exception) . ': ' . $exception->getMessage();
        
        if ($context) {
            $message = "[{$context}] {$message}";
        }
        
        $message .= " in {$exception->getFile()}:{$exception->getLine()}";
        
        return $message;
    }

    /**
     * Check if an exception should be ignored (for known non-critical errors)
     */
    public static function shouldIgnore(Throwable $exception): bool
    {
        // Define patterns for exceptions that can be safely ignored
        $ignorablePatterns = [
            'stream_context_create', // Common in some environments
            'file_get_contents.*HTTP request failed', // Network timeouts
        ];

        $message = $exception->getMessage();
        foreach ($ignorablePatterns as $pattern) {
            if (preg_match("/{$pattern}/i", $message)) {
                return true;
            }
        }

        return false;
    }
}