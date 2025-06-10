<?php

declare(strict_types=1);

namespace LaminasMicroscope\Utility;

use function count;
use function floor;
use function log;
use function max;
use function memory_get_peak_usage;
use function memory_get_usage;
use function min;
use function round;

/**
 * Shared formatting utilities for Laminas Microscope
 */
class FormatUtility
{
    /**
     * Format duration in seconds to a human-readable string (ms or µs)
     */
    public static function formatDuration(float $seconds): string
    {
        if ($seconds < 0.001) {
            return round($seconds * 1000000) . 'µs';
        } elseif ($seconds < 1) {
            return round($seconds * 1000, 2) . 'ms';
        }

        return round($seconds, 2) . 's';
    }

    /**
     * Format bytes to human readable format
     */
    public static function formatBytes(int $size, int $precision = 2): string
    {
        if ($size === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($size, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);

        $bytes /= 1 << (10 * $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Format memory usage from functions.php into shared utility
     */
    public static function formatMemoryUsage(bool $realUsage = true): string
    {
        return self::formatBytes(memory_get_usage($realUsage));
    }

    /**
     * Format peak memory usage from functions.php into shared utility
     */
    public static function formatPeakMemoryUsage(bool $realUsage = true): string
    {
        return self::formatBytes(memory_get_peak_usage($realUsage));
    }
}
