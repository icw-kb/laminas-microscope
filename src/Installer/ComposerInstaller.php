<?php

declare(strict_types=1);

namespace LaminasMicroscope\Installer;

use Composer\Script\Event;
use Composer\IO\IOInterface;

/**
 * Composer post-install/update scripts
 */
class ComposerInstaller
{
    public static function postInstall(Event $event): void
    {
        self::displayWelcomeMessage($event->getIO());
        self::checkRequirements($event->getIO());
        self::createDirectories($event->getIO());
    }

    public static function postUpdate(Event $event): void
    {
        self::displayUpdateMessage($event->getIO());
        self::checkRequirements($event->getIO());
    }

    private static function displayWelcomeMessage(IOInterface $io): void
    {
        $io->write([
            '',
            '🔬 <info>Laminas Microscope installed successfully!</info>',
            '',
            '<comment>Getting started:</comment>',
            '  1. Add LaminasMicroscope to your modules.config.php',
            '  2. Visit /_debug in your browser to access the debug dashboard',
            '  3. Configure components in your application config',
            '',
            '<comment>Documentation:</comment>',
            '  • GitHub: https://github.com/icw-kb/laminas-microscope',
            '  • Docs: https://laminas-microscope.readthedocs.io',
            '',
        ]);
    }

    private static function displayUpdateMessage(IOInterface $io): void
    {
        $io->write([
            '',
            '🔬 <info>Laminas Microscope updated successfully!</info>',
            '',
            '<comment>What\'s new?</comment>',
            '  • Check the CHANGELOG.md for latest features',
            '  • Visit /_debug to see updated interface',
            '',
        ]);
    }

    private static function checkRequirements(IOInterface $io): void
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $io->writeError('⚠️  PHP 8.1 or higher is required');
        } else {
            $io->write('✅ PHP version: ' . PHP_VERSION);
        }

        // Check extensions
        $extensions = ['json', 'mbstring'];
        foreach ($extensions as $ext) {
            if (!extension_loaded($ext)) {
                $io->writeError("⚠️  Required extension missing: {$ext}");
            }
        }

        // Check optional extensions
        $optionalExtensions = ['xdebug', 'opcache'];
        foreach ($optionalExtensions as $ext) {
            if (extension_loaded($ext)) {
                $io->write("✅ Optional extension available: {$ext}");
            } else {
                $io->write("💡 Consider installing optional extension: {$ext}");
            }
        }
    }

    private static function createDirectories(IOInterface $io): void
    {
        $directories = [
            'data/laminas-microscope',
            'data/laminas-microscope/reports',
            'data/laminas-microscope/cache',
            'data/laminas-microscope/logs',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    $io->write("📁 Created directory: {$dir}");
                } else {
                    $io->writeError("❌ Failed to create directory: {$dir}");
                }
            }
        }

        // Create .gitignore for data directory
        $gitignoreContent = "*\n!.gitignore\n";
        file_put_contents('data/laminas-microscope/.gitignore', $gitignoreContent);
    }
}
