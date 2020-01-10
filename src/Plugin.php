<?php

/**
 * @file
 * Plugin.php
 *
 * @author Josh Parkinson <joshparkinson1991@gmail.com>
 */

namespace JParkinson1991\ComposerLinkerPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use JParkinson1991\ComposerLinkerPlugin\Config\Config;
use JParkinson1991\ComposerLinkerPlugin\Config\ConfigLoadException;
use JParkinson1991\ComposerLinkerPlugin\Package\PackageUtil;
use JParkinson1991\ComposerLinkerPlugin\Package\UnhandledPackageOperationException;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Plugin
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Plugin
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * The name of the plugin options expected within the composer.json extra
     * object.
     */
    public const EXTRA_KEY = 'linker-plugin';

    /**
     * The plugin configuration instance
     *
     * @var null|Config
     */
    protected $config = null;

    /**
     * Local filesystem instance
     *
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * The root directory
     *
     * @var string
     */
    protected $rootDir;

    /**
     * Actives the plugin
     *
     * @param Composer $composer
     *    The main container object
     * @param IOInterface $io
     *    The io object
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->fileSystem = new Filesystem();
        $this->rootDir = realpath(getcwd());

        /* @var RootPackageInterface $package */
        $package = $composer->getPackage();
        $extra =  $package->getExtra();

        // Do not set config if the config key not found in extra
        // This implicitly disables this plugin
        if (!empty($extra[self::EXTRA_KEY])) {
            try {
                $this->config = Config::fromArray($extra[self::EXTRA_KEY]);
            } catch (ConfigLoadException $e) {
                throw new RuntimeException(
                    'Composer Linker Plugin - ' . $e->getMessage()
                );
            }
        }
    }

    /**
     * Returns the events handled by this plugin
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'link',
            PackageEvents::POST_PACKAGE_UPDATE => 'link',
            PackageEvents::POST_PACKAGE_UNINSTALL => 'unlink'
        ];
    }

    /**
     * Handles the linking of a package after its been installed or updated.
     *
     * Package is first extracted from the package event and if required
     * is linked using this plugin.
     *
     * @param PackageEvent $event
     *     The package event, triggered by install or update
     *
     * @retrun void
     */
    public function link(PackageEvent $event): void
    {
        // No config, disable plugin
        if ($this->config === null) {
            return;
        }

        // Attempt to extract package meta, aborting processing or error.
        try {
            $package = PackageUtil::getPackageFromEvent($event);
            $packageInstallPath = PackageUtil::getPackageInstallPathFromEvent($event);
        } catch (UnhandledPackageOperationException $e) {
            $event->getIO()->writeError('Composer Linker Plugin - ' . $e->getMessage());
            return;
        }

        // If this plugin does not handle the package stop processing
        if (!$this->config->packageHasConfig($package)) {
            return;
        }

        // Pull mapping configuration for the package
        $packageMappedDir = $this->rootDir . '/' . $this->config->getPackageLinkDir($package);
        $packageMappedFiles = $this->config->getPackageLinkFiles($package);
        $copy = $this->config->packageUsesCopy($package);

        // Delete any already created directory/symlinks
        if ($this->fileSystem->exists($packageMappedDir)) {
            $this->fileSystem->remove($packageMappedDir);
        }

        // No specific files provided, copy/link the entire directory
        // Else map each file one by one
        if (empty($packageMappedFiles)) {
            if ($copy) {
                $this->fileSystem->copy($packageInstallPath, $packageMappedDir);
            } else {
                $this->fileSystem->symlink($packageInstallPath, $packageMappedDir, true);
            }
        } else {
            foreach ($packageMappedFiles as $mapping) {
                $sourcePath = $packageInstallPath . '/' . $mapping['source'];
                $destPath = $packageMappedDir . '/' . $mapping['dest'];

                // Possible typo's for custom provided paths, ensure it exists
                // prior to link
                if ($this->fileSystem->exists($sourcePath)) {
                    if ($copy) {
                        $this->fileSystem->copy($sourcePath, $destPath);
                    } else {
                        $this->fileSystem->symlink($sourcePath, $destPath, true);
                    }
                }
            }
        }
    }

    /**
     * Handles the unlinking of package after it has been removed.
     *
     * Package is first extracted from the package event and if required
     * all linked files associated with it are removed.
     *
     * NOTE: The mapped directory is unlinked in its entirety regardless of
     * whether package config only maps specific files. Because of this it is
     * strongly recommended to never manually place files into the mapped
     * package directory.
     *
     * @param PackageEvent $event
     */
    public function unlink(PackageEvent $event)
    {
        // No config, disable plugin
        if ($this->config === null) {
            return;
        }

        // Extract package metadata, end processing on error
        try {
            $package = PackageUtil::getPackageFromEvent($event);
        } catch (UnhandledPackageOperationException $e) {
            $event->getIO()->writeError('Composer Linker Plugin - ' . $e->getMessage());

            return;
        }

        // No config for package, assume unhandled, stop processing
        if (!$this->config->packageHasConfig($package)) {
            return;
        }

        // Blindly remove the entire contents of the package link directory.
        $this->fileSystem->remove(
            $this->rootDir . '/' . $this->config->getPackageLinkDir($package)
        );
    }
}
