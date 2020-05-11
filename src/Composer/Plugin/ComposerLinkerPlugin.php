<?php
/**
 * @file
 * ComposerLinkerPlugin.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Plugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Exception;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractor;
use JParkinson1991\ComposerLinkerPlugin\Config\LinkConfigLocator;
use JParkinson1991\ComposerLinkerPlugin\Config\LinkConfigFactory;
use JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException;
use JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException;
use JParkinson1991\ComposerLinkerPlugin\Files\LinkFileHandler;
use JParkinson1991\ComposerLinkerPlugin\Log\SimpleIoLogger;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ComposerLinkerPlugin
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Composer
 */
class ComposerLinkerPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * The key in which this plugin expects to find its config under the
     * 'extra' section of the composer.json file
     */
    public const PLUGIN_CONFIG_KEY = 'composer-linker-plugin';

    /**
     * Indication on whether plugin was activated successfully.
     *
     * @var bool
     */
    protected $isActivated = false;

    /**
     * The package link file handler instance
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Files\LinkFileHandler
     */
    protected $fileHandler;

    /**
     * The local package extractor instance
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractor
     */
    protected $packageExtractor;

    /**
     * Actives this plugin
     *
     * @param \Composer\Composer $composer
     * @param \Composer\IO\IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        // Initialise class properties
        // Do this here rather than constructor as some services require
        // composer dependents (ie installation manager) and this keeps all
        // plugin pre configs, activation, set up etc together
        $ioLogger = new SimpleIoLogger($io);
        $this->fileHandler = new LinkFileHandler(
            new Filesystem(),
            $composer->getInstallationManager()
        );
        $this->fileHandler->setLogger($ioLogger);
        $this->packageExtractor = new PackageExtractor();


        // Use the configuration locator to find the raw plugin config as
        // defined in the root package, validate the plugin config structure
        // at this point as this should stop process on fail whereas package
        // configure errors can be skipped.
        try {
            $configLocator = new LinkConfigLocator();
            $pluginConfig = $configLocator->locateInRootPackage(
                $composer->getPackage(),
                self::PLUGIN_CONFIG_KEY
            );

            if (!is_array($pluginConfig)) {
                throw InvalidConfigException::pluginConfigNotAnArray();
            }
        } catch (ConfigNotFoundException | InvalidConfigException $e) {
            $io->writeError([
                'Composer Linker Plugin: Aborting',
                '> '.$e->getMessage()
            ]);

            return;
        }

        $configFactory = new LinkConfigFactory();
        foreach ($pluginConfig as $packageName => $packageConfig) {
            try {
                // Each config definition is expected to be key'd by the name of
                // the package it is to be associated with, rather than do full
                // validation on package name, ensure it is a string so it at least
                // has a fighting change
                if (!is_string($packageName)) {
                    throw InvalidConfigException::packageNameNotString($packageName);
                }

                $this->fileHandler->addConfig($configFactory->create($packageName, $packageConfig));

                // Set is activated here, config creation and adding to file
                // handler will trigger exceptions if this statement reached
                // the file handler has a config attached to it and this plugin
                // is active.
                $this->isActivated = true;
            } catch (InvalidConfigException $e) {
                $io->writeError([
                    'Composer Link Plugin: Skipping package '.$packageName,
                    '> '.$e->getMessage()
                ]);
            }
        }
    }

    /**
     * Returns plugin activation status
     *
     * @return bool
     */
    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    /**
     * Handles linking of package's files as per plugin configuration during
     * its lifecycle.
     *
     * This method is triggered on package install/update, once invoked it
     * determines whether configuration was defined for the package in
     * which the event was triggered for. If plugin config exists (ie, was
     * loaded into the file handler on activation) then the link method of
     * the file handler is triggered for the package.
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function linkPackageFromEvent(PackageEvent $event): void
    {
        if ($this->isActivated() === false) {
            return;
        }

        $package = $this->packageExtractor->extractFromEvent($event);
        if ($this->fileHandler->supportsPackage($package) === false) {
            return;
        }

        $event->getIO()->write('<info>Composer Linker Plugin: Linking '.$package->getName().'</info>');
        $this->fileHandler->link($package);
    }

    /**
     * TODO
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function unlinkPackageFromEvent(PackageEvent $event): void
    {
        if ($this->isActivated() === false) {
            return;
        }

        $package = $this->packageExtractor->extractFromEvent($event);
        if ($this->fileHandler->supportsPackage($package) === false) {
            return;
        }

        $event->getIO()->write('<info>Composer Linker Plugin: Unlinking '.$package->getName().'</info>');
        $this->fileHandler->unlink($package);
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'linkPackageFromEvent',
            PackageEvents::POST_PACKAGE_UPDATE => 'linkPackageFromEvent',
            PackageEvents::POST_PACKAGE_UNINSTALL => 'unlinkPackageFromEvent'
        ];
    }
}
