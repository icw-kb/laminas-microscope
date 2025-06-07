<?php

/**
 * Global helper functions for Laminas Microscope
 */

declare(strict_types=1);

if (!function_exists('microscope_dump')) {
    /**
     * Dump variable using Symfony VarDumper if available
     */
    function microscope_dump(...$vars): void
    {
        if (class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
            foreach ($vars as $var) {
                \Symfony\Component\VarDumper\VarDumper::dump($var);
            }
        } else {
            foreach ($vars as $var) {
                var_dump($var);
            }
        }
    }
}

if (!function_exists('microscope_dd')) {
    /**
     * Dump and die
     */
    function microscope_dd(...$vars): never
    {
        microscope_dump(...$vars);
        die(1);
    }
}

if (!function_exists('microscope_measure')) {
    /**
     * Quick measurement helper
     */
    function microscope_measure(string $name, callable $callback): mixed
    {
        $start = microtime(true);
        $result = $callback();
        $end = microtime(true);

        if (class_exists(\LaminasMicroscope\DebugBar\DebugBarHandler::class)) {
            $debugBar = \LaminasMicroscope\Registry::getDebugBar();
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

if (!function_exists('microscope_log')) {
    /**
     * Quick logging helper
     */
    function microscope_log(string $message, string $level = 'info', array $context = []): void
    {
        if (class_exists(\LaminasMicroscope\DebugBar\DebugBarHandler::class)) {
            $debugBar = \LaminasMicroscope\Registry::getDebugBar();
            if ($debugBar && $debugBar->isEnabled()) {
                $debugBar->addMessage($message, $level);
            }
        }
    }
}

if (!function_exists('microscope_memory')) {
    /**
     * Get current memory usage
     */
    function microscope_memory(bool $realUsage = true): string
    {
        $bytes = memory_get_usage($realUsage);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}

if (!function_exists('microscope_peak_memory')) {
    /**
     * Get peak memory usage
     */
    function microscope_peak_memory(bool $realUsage = true): string
    {
        $bytes = memory_get_peak_usage($realUsage);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
