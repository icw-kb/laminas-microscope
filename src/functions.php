<?php

declare(strict_types=1);

use LaminasMicroscope\DebugBar\DebugBarHandler;
use LaminasMicroscope\Registry;
use LaminasMicroscope\Utility\FormatUtility;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Global helper functions for Laminas Microscope
 */

if (! function_exists('microscope_dump')) {
    /**
     * Dump variable using Symfony VarDumper if available
     */
    function microscope_dump(...$vars): void
    {
        if (class_exists(VarDumper::class)) {
            foreach ($vars as $var) {
                VarDumper::dump($var);
            }
        } else {
            foreach ($vars as $var) {
                var_dump($var);
            }
        }
    }
}

if (! function_exists('microscope_dd')) {
    /**
     * Dump and die
     */
    function microscope_dd(...$vars): never
    {
        microscope_dump(...$vars);
        die(1);
    }
}

if (! function_exists('microscope_measure')) {
    /**
     * Quick measurement helper
     */
    function microscope_measure(string $name, callable $callback): mixed
    {
        $start  = microtime(true);
        $result = $callback();
        $end    = microtime(true);

        if (class_exists(DebugBarHandler::class)) {
            $debugBar = Registry::getDebugBar();
            if ($debugBar && $debugBar->isEnabled()) {
                $debugBar->addMessage(
                    sprintf('Measurement "%s": %.2fms', $name, ($end - $start) * 1000),
                    'info'
                );
            }
        }

        return $result;
    }
}

if (! function_exists('microscope_log')) {
    /**
     * Quick logging helper
     */
    function microscope_log(string $message, string $level = 'info', array $context = []): void
    {
        if (class_exists(DebugBarHandler::class)) {
            $debugBar = Registry::getDebugBar();
            if ($debugBar && $debugBar->isEnabled()) {
                $debugBar->addMessage($message, $level);
            }
        }
    }
}

if (! function_exists('microscope_memory')) {
    /**
     * Get current memory usage
     */
    function microscope_memory(bool $realUsage = true): string
    {
        return FormatUtility::formatMemoryUsage($realUsage);
    }
}

if (! function_exists('microscope_peak_memory')) {
    /**
     * Get peak memory usage
     */
    function microscope_peak_memory(bool $realUsage = true): string
    {
        return FormatUtility::formatPeakMemoryUsage($realUsage);
    }
}
