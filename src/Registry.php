<?php

declare(strict_types=1);

namespace LaminasMicroscope;

use LaminasMicroscope\DebugBar\DebugBarHandler;
use LaminasMicroscope\Microscope\MicroscopeHandler;
use LaminasMicroscope\Whoops\WhoopsHandler;

/**
 * Global registry for Microscope components
 */
class Registry
{
    private static ?DebugBarHandler $debugBar = null;
    private static ?MicroscopeHandler $microscope = null;
    private static ?WhoopsHandler $whoops = null;

    public static function setDebugBar(DebugBarHandler $debugBar): void
    {
        self::$debugBar = $debugBar;
    }

    public static function getDebugBar(): ?DebugBarHandler
    {
        return self::$debugBar;
    }

    public static function setMicroscope(MicroscopeHandler $microscope): void
    {
        self::$microscope = $microscope;
    }

    public static function getMicroscope(): ?MicroscopeHandler
    {
        return self::$microscope;
    }

    public static function setWhoops(WhoopsHandler $whoops): void
    {
        self::$whoops = $whoops;
    }

    public static function getWhoops(): ?WhoopsHandler
    {
        return self::$whoops;
    }

    public static function clear(): void
    {
        self::$debugBar = null;
        self::$microscope = null;
        self::$whoops = null;
    }
}
