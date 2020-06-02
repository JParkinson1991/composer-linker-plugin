<?php
/**
 * @file
 * ComposerLinkerPLugin.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Plugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Util\Filesystem as ComposerFileSystem;
use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\ComposerLinkerPluginCommandProvider;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractor;
use JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorExceptionCollection;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandler;
use JParkinson1991\ComposerLinkerPlugin\Log\SimpleIoLogger;
use Symfony\Component\Filesystem\Filesystem as SymfonyFileSystem;

/**
 * Class ComposerLinkerPlugin
 *
 * @psalm-suppress MissingConstructor
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Composer\Plugin
 */
class ComposerLinkerPlugin implements PluginInterface, Capable, EventSubscriberInterface
{

    /**
     * The local link executor
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor
     */
    protected $linkExecutor;

    /**
     * The local package extractor instance
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractor
     */
    protected $packageExtractor;

    /**
     * @inheritDoc
     *
     * @throws \Exception
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->packageExtractor = new PackageExtractor();

        $linkDefinitionFactory = new LinkDefinitionFactory($composer->getPackage());

        $linkFileHandler = new LinkFileHandler(
            new SymfonyFileSystem(),
            new ComposerFileSystem(),
            $composer->getInstallationManager()
        );
        $linkFileHandler->setLogger(new SimpleIoLogger($io));
        $linkFileHandler->setRootPath(dirname($composer->getConfig()->get('vendor-dir')));

        $this->linkExecutor = new LinkExecutor(
            $linkDefinitionFactory,
            $linkFileHandler
        );
    }

    /**
     * @inheritDoc
     */
    public function getCapabilities()
    {
        return [
            CommandProvider::class => ComposerLinkerPluginCommandProvider::class
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => ['initPlugin', 'linkPackageFromEvent'],
            PackageEvents::POST_PACKAGE_UPDATE => 'linkPackageFromEvent',
            PackageEvents::PRE_PACKAGE_UNINSTALL => 'cleanUpPlugin',
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
            $this->linkExecutor->linkPackage($package);
        }
        catch (PackageExtractionUnhandledEventOperationException | LinkExecutorException $e) {
            if (
                $e instanceof PackageExtractionUnhandledEventOperationException
                || (
                    $e instanceof LinkExecutorException
                    && !$e->getExecutionException() instanceof ConfigNotFoundException
                )
            ) {
                $event->getIO()->writeError([
                    'Composer Linker Plugin: Error',
                    '> '.$e->getMessage()
                ]);
            }
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
            $this->linkExecutor->unlinkPackage($package);
        }
        catch (PackageExtractionUnhandledEventOperationException | LinkExecutorException $e) {
            if (
                $e instanceof PackageExtractionUnhandledEventOperationException
                || (
                    $e instanceof LinkExecutorException
                    && !$e->getExecutionException() instanceof ConfigNotFoundException
                )
            ) {
                $event->getIO()->writeError([
                    'Composer Linker Plugin: Error',
                    '> '.$e->getMessage()
                ]);
            }
        }
    }

    /**
     * Runs the initial link for any found plugin configuration as it is
     * installed.
     *
     * Different from link package from event method as this method only runs
     * when the plugin itself is installed and will run link execution for
     * all packages that have config defined.
     *
     * Useful in the scenario when someone configs the plugin before requiring
     * it in their project. No need to manually link after plugin install etc/
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @return void
     */
    public function initPlugin(PackageEvent $event): void
    {
        $package = $this->packageExtractor->extractFromEvent($event);
        if ($package->getName() !== 'jparkinson1991/composer-linker-plugin') {
            return;
        }

        $packageRepository = $event->getComposer()->getRepositoryManager()->getLocalRepository();
        $event->getIO()->write('Initialising <info>'.$package->getName().'</info> links');

        try {
            $this->linkExecutor->linkRepository($packageRepository);
        }
        catch (LinkExecutorExceptionCollection $collection) {
            $event->getIO()->writeError('<error>Error</error> Initialisation resulted in errors');
            foreach ($collection->getExceptions() as $linkExecutorException) {
                $event->getIO()->writeError(sprintf(
                    '<info>%s</info>: %s',
                    $linkExecutorException->getPackage()->getName(),
                    $linkExecutorException->getExecutionException()->getMessage()
                ));
            }
        }
    }

    /**
     * Cleans up all links created by this plugin prior to it's uninstallation
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function cleanUpPlugin(PackageEvent $event): void
    {
        $package = $this->packageExtractor->extractFromEvent($event);
        if ($package->getName() !== 'jparkinson1991/composer-linker-plugin') {
            return;
        }

        $packageRepository = $event->getComposer()->getRepositoryManager()->getLocalRepository();
        $event->getIO()->write('Cleaning up <info>'.$package->getName().'</info> links');

        try {
            $this->linkExecutor->unlinkRepository($packageRepository);
        }
        catch (LinkExecutorExceptionCollection $collection) {
            $event->getIO()->writeError('<error>Error</error> Cleanup resulted in errors');
            foreach ($collection->getExceptions() as $linkExecutorException) {
                $event->getIO()->writeError(sprintf(
                    '<info>%s</info>: %s',
                    $linkExecutorException->getPackage()->getName(),
                    $linkExecutorException->getExecutionException()->getMessage()
                ));
            }
        }
    }
}
