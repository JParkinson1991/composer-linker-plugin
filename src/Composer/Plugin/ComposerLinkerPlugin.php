<?php
/**
 * @file
 * ComposerLinkerPLugin.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Plugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Util\Filesystem as ComposerFileSystem;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractor;
use JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException;
use JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandler;
use JParkinson1991\ComposerLinkerPlugin\Log\SimpleIoLogger;
use Symfony\Component\Filesystem\Filesystem as SymfonyFileSystem;

/**
 * Class ComposerLinkerPlugin
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Composer\Plugin
 */
class ComposerLinkerPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * The local link definition factory
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory
     */
    protected $linkDefinitionFactory;

    /**
     * The local link file handler instance
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandler
     */
    protected $linkFileHandler;

    /**
     * The local package extractor instance
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractorInterface
     */
    protected $packageExtractor;

    /**
     * @inheritDoc
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->packageExtractor = new PackageExtractor();
        $this->linkDefinitionFactory = new LinkDefinitionFactory($composer->getPackage());
        $this->linkFileHandler = new LinkFileHandler(
            new SymfonyFileSystem(),
            new ComposerFileSystem(),
            $composer->getInstallationManager()
        );
        $this->linkFileHandler->setLogger(new SimpleIoLogger($io));
        $this->linkFileHandler->setRootPath(dirname($composer->getConfig()->get('vendor-dir')));
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'linkPackageFromEvent',
            PackageEvents::POST_PACKAGE_UPDATE => 'linkPackageFromEvent',
            PackageEvents::POST_PACKAGE_UNINSTALL => 'unlinkPackageFromEvent'
        ];
    }

    /**
     * Attempts to link files for package after it has been installed or
     * updated.
     *
     * This method extracts the package from the triggered event before
     * attempting to create a link definition instance for it. If a link
     * definition is created that instance is passed to the file handler
     * to link the package's files.
     *
     * @param \Composer\Installer\PackageEvent $event
     *     The package containing event triggered during package install or
     *     update
     *
     * @return void
     */
    public function linkPackageFromEvent(PackageEvent $event): void
    {
        try {
            // Extract the package, create a link definition instance for it
            $package = $this->packageExtractor->extractFromEvent($event);
            $linkDefinition = $this->linkDefinitionFactory->createForPackage($package);

            // Do not catch link exceptions
            // This allows composer to revert installation if linking failed
            $this->linkFileHandler->link($linkDefinition);
        }
        catch (PackageExtractionUnhandledEventOperationException | InvalidConfigException $e) {
            $event->getIO()->writeError([
                'Composer Linker Plugin: Error',
                '> '.$e->getMessage()
            ]);
        }
        catch (ConfigNotFoundException $e) {
            // Skip unhandled packages
        }
    }

    /**
     * Attempts to unlink the files for package after it has been uninstalled.
     *
     * This method extracts the package from the triggered uninstall event,
     * attempts to create a link definition for it and passes that off to
     * the link file handler for unlinking.
     *
     * @param \Composer\Installer\PackageEvent $event
     *     The package containing uninstallation event
     *
     * @return void
     */
    public function unlinkPackageFromEvent(PackageEvent $event): void
    {
        try {
            // Extract the package, create a link definition instance for it
            $package = $this->packageExtractor->extractFromEvent($event);
            $linkDefinition = $this->linkDefinitionFactory->createForPackage($package);

            $this->linkFileHandler->unlink($linkDefinition);
        }
        catch (PackageExtractionUnhandledEventOperationException | InvalidConfigException $e) {
            $event->getIO()->writeError([
                'Composer Linker Plugin: Error',
                '> '.$e->getMessage()
            ]);
        }
        catch (ConfigNotFoundException $e) {
            // Skip unhandled packages
        }
    }
}
