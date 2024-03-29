<?php
/**
 * @file
 * ComposerLinkerPluginTest.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Plugin;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Exception;
use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\ComposerLinkerPluginCommandProvider;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractor;
use JParkinson1991\ComposerLinkerPlugin\Composer\Plugin\ComposerLinkerPlugin;
use JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException;
use JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorExceptionCollection;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor;
use JParkinson1991\ComposerLinkerPlugin\Tests\Support\ReflectionMutatorTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class ComposerLinkerPluginTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Plugin
 */
class ComposerLinkerPluginTest extends TestCase
{
    /**
     * Allow setting of protected/private object properties via mutation
     */
    use ReflectionMutatorTrait;

    /**
     * A mocked link executor class used within the plugin property of
     * this class
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $linkExecutor;

    /**
     * A mocked package extractor service that is used within the plugin
     * property of this class
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractor|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $packageExtractor;

    /**
     * The activated plugin instance ready for test
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Composer\Plugin\ComposerLinkerPlugin
     */
    protected $plugin;

    /**
     * Sets up this class prior to executing each test case within it
     *
     * Essentially creates an activated instance of the plugin with
     * mockable services that can be used in the test cases
     *
     * @return void
     *
     * @throws \ReflectionException
     *
     * @throws \Exception
     */
    public function setUp(): void
    {
        // Create a mock composer config class returning a valid directory
        $composerConfig = $this->createMock(Config::class);
        $composerConfig
            ->method('get')
            ->with('vendor-dir')
            ->willReturn(__DIR__);

        // Create a mocked composer class for use in plugin activation
        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getPackage')
            ->willReturn($this->createMock(RootPackageInterface::class));
        $composer
            ->method('getInstallationManager')
            ->willReturn($this->createMock(InstallationManager::class));
        $composer
            ->method('getConfig')
            ->willReturn($composerConfig);

        // Create a mocked IO class to be used in plugin activation
        $io = $this->createMock(IOInterface::class);

        // Initialise and activate the plugin
        $composerLinkerPlugin = new ComposerLinkerPlugin();
        $composerLinkerPlugin->activate($composer, $io);

        // Inject a mocked package extractor
        $packageExtractor = $this->createMock(PackageExtractor::class);
        $this->setPropertyValue($composerLinkerPlugin, 'packageExtractor', $packageExtractor);

        $linkExecutor = $this->createMock(LinkExecutor::class);
        $this->setPropertyValue($composerLinkerPlugin, 'linkExecutor', $linkExecutor);

        // Set to properties for access via each test case
        // Objects passed by reference
        // Altering class properties will alter plugin services
        $this->plugin = $composerLinkerPlugin;
        $this->packageExtractor = $packageExtractor;
        $this->linkExecutor = $linkExecutor;
    }

    /**
     * Empty deactivation test for coverage purposes
     *
     * To be updated if ever deactivation method used
     *
     * @return void
     */
    public function testDeactivation(): void
    {
        $this->plugin->deactivate(
            $this->createMock(Composer::class),
            $this->createMock(IOInterface::class)
        );

        $this->assertTrue(true);
    }

    /**
     * Empty uninstallation test for coverage purposes
     *
     * To be updated if ever deactivation method used
     *
     * @return void
     */
    public function testUninstallation(): void
    {
        $this->plugin->uninstall(
            $this->createMock(Composer::class),
            $this->createMock(IOInterface::class)
        );

        $this->assertTrue(true);
    }

    /**
     * Tests that the plugin is subscribed to the expected events
     *
     * @return void
     */
    public function testSubscribedToExpectedEvents(): void
    {
        $plugin = new ComposerLinkerPlugin();

        $this->assertInstanceOf(
            EventSubscriberInterface::class,
            $plugin
        );

        $subscribedEvents = $plugin::getSubscribedEvents();

        $this->assertCount(4, $subscribedEvents);
        $this->assertArrayHasKey(PackageEvents::POST_PACKAGE_INSTALL, $subscribedEvents);
        $this->assertArrayHasKey(PackageEvents::POST_PACKAGE_UPDATE, $subscribedEvents);
        $this->assertArrayHasKey(PackageEvents::PRE_PACKAGE_UNINSTALL, $subscribedEvents);
        $this->assertArrayHasKey(PackageEvents::POST_PACKAGE_UNINSTALL, $subscribedEvents);
    }

    /**
     * Tests that the plugin has command providing capabilities
     *
     * @return void
     */
    public function testItProvidesCommands(): void
    {
        $plugin = new ComposerLinkerPlugin();

        $this->assertInstanceOf(Capable::class, $plugin);

        $this->assertArrayHasKey(CommandProvider::class, $plugin->getCapabilities());
        $this->assertSame(
            ComposerLinkerPluginCommandProvider::class,
            $plugin->getCapabilities()[CommandProvider::class]
        );
    }

    /**
     * Tests that the plugin exits and outputs an error message if package
     * extraction throws an exception
     *
     * @return void
     */
    public function testPackageLinkingExitsWithErrorOnExtractionException(): void
    {
        // Create mock package event
        // Dont use protected helper method here as it's not needed to
        // return a package
        $event = $this->createMock(PackageEvent::class);

        // Have the package extractor throw an exception when receving
        // the test event
        $this->packageExtractor
            ->method('extractFromEvent')
            ->with($event)
            ->willThrowException(new PackageExtractionUnhandledEventOperationException());

        // Mock an IO instance for observation, ensure it logs an error
        // inject it into event
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('writeError');
        $event->method('getIO')->willReturn($io);

        // Unlink should not be called on extraction error
        $this->linkExecutor
            ->expects($this->never())
            ->method('linkPackage');

        $this->plugin->linkPackageFromEvent($event);
    }

    /**
     * Test that the plugin will silently exist if no config found for the
     * package that trigger the post package install/update event.
     *
     * This is not an error, thus should not be treated as one
     *
     * @return void
     */
    public function testPackageLinkingSilentlyExitsOnConfigNotFound(): void
    {
        // Create a test package, and a configured event that contains it
        $package = new Package('test/package', '1.0.0', '1');

        $exception = $this->createMock(LinkExecutorException::class);
        $exception
            ->method('getExecutionException')
            ->willReturn(new ConfigNotFoundException());

        // Configure the definition factory to throw a config not found
        // exception when encountering our test package
        $this->linkExecutor
            ->method('linkPackage')
            ->with($package)
            ->willThrowException($exception);

        // Mock an io instance, asserting no logging/output methods called
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->never())->method($this->anything());

        // Create an event that uses the mocked io
        $event = $this->createConfiguredEventReturningPackage($package);
        $event->method('getIo')->willReturn($io);

        // Trigger the link method
        // This should cause a config not found error, by default the plugin
        // property on this class has been configured with a root package
        // that will return an empty extra array. The extra array is where
        // this config should be found
        $this->plugin->linkPackageFromEvent($event);
    }

    /**
     * Tests that the plugin exists and outputs an error message if a package
     * config is deemed to be invalid
     *
     * @return void
     */
    public function testPackageLinkingExitsWithOnInvalidConfigException(): void
    {
        $package = new Package('test/package', '1.0.0', '1');

        $exception = $this->createMock(LinkExecutorException::class);
        $exception
            ->method('getExecutionException')
            ->willReturn(new InvalidConfigException());

        $this->linkExecutor
            ->method('linkPackage')
            ->with($package)
            ->willThrowException($exception);

        // Mock an IO instance for observation, ensure it logs an error
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('writeError');

        $event = $this->createConfiguredEventReturningPackage($package);
        $event->method('getIO')->willReturn($io);

        $this->plugin->linkPackageFromEvent($event);
    }

    /**
     * Tests that given no errors the plugin will actually attempt to link
     * a package's files.
     *
     * That is, when a package has associated config, it is extracted
     * successfully from the package event then it should be passed to the
     * file handler for linking.
     *
     * @return void
     */
    public function testItRunsPackageLinking(): void
    {
        $package = new Package('test/package', '1.0.0', '1');
        $event = $this->createConfiguredEventReturningPackage($package);

        $this->linkExecutor
            ->expects($this->once())
            ->method('linkPackage')
            ->with($package);

        $this->plugin->linkPackageFromEvent($event);
    }

    /**
     * Tests that the plugin exits during unlinking if the package can not be
     * extracted from the given event.
     *
     * @return void
     */
    public function testUnlinkExitsWithErrorOnExtractionException(): void
    {
        // Create simple event mock
        // Doesn't need to return package so mock directly rather than through
        // helper.
        $event = $this->createMock(PackageEvent::class);

        // Have the package extractor throw exception for this event
        $this->packageExtractor
            ->method('extractFromEvent')
            ->with($event)
            ->willThrowException(new PackageExtractionUnhandledEventOperationException());

        // Mock io instance for observation, inject into event
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('writeError');
        $event->method('getIO')->willReturn($io);

        // Unlink should not be called on extraction error
        $this->linkExecutor
            ->expects($this->never())
            ->method('unlinkPackage');

        $this->plugin->unlinkPackageFromEvent($event);
    }

    /**
     * Tests that during package unlinking (ie, when triggered by the package
     * uninstall event) that if no config is found for the given package then
     * the plugin will silently exit, no error, no exception, no log
     *
     * @return void
     */
    public function testUnlinkingSilentlyExitsOnConfigNotFound(): void
    {
        // Create the test package and event
        $package = new Package('test/package', '1.0.0', '1');
        $event = $this->createConfiguredEventReturningPackage($package);

        $exception = $this->createMock(LinkExecutorException::class);
        $exception
            ->method('getExecutionException')
            ->willReturn(new ConfigNotFoundException());

        // Have the link definition factory return not found exception
        $this->linkExecutor
            ->method('unlinkPackage')
            ->with($package)
            ->willThrowException($exception);

        // Mock an IO instance so logging/output can be monitored
        // Inject into event
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->never())->method($this->anything());
        $event->method('getIo')->willReturn($io);

        $this->plugin->unlinkPackageFromEvent($event);
    }

    /**
     * Tests that the plugin exists during unlinking if the package has
     * invalid config associated with it
     *
     * @return void
     */
    public function testUnlinkExitsWithErrorOnInvalidConfig(): void
    {
        $package = new Package('test/package', '1.0.0', '1');

        $exception = $this->createMock(LinkExecutorException::class);
        $exception
            ->method('getExecutionException')
            ->willReturn(new InvalidConfigException());

        $this->linkExecutor
            ->method('unlinkPackage')
            ->with($package)
            ->willThrowException($exception);

        // Mock an IO instance for observation, ensure it logs an error
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('writeError');

        $event = $this->createConfiguredEventReturningPackage($package);
        $event->method('getIO')->willReturn($io);

        $this->plugin->unlinkPackageFromEvent($event);
    }

    /**
     * That package unlinking is attempted when a handled package with
     * valid config is extracted from the uninstall event
     *
     * @return void
     */
    public function testItRunsPackageUnlinking(): void
    {
        $package = new Package('test/package', '1.0.0', '1');
        $event = $this->createConfiguredEventReturningPackage($package);

        $this->linkExecutor
            ->expects($this->once())
            ->method('unlinkPackage')
            ->with($package);

        $this->plugin->unlinkPackageFromEvent($event);
    }

    /**
     * Tests that plugin initialisation is not executed if a package is
     * installed that is not this plugin.
     *
     * @return void
     */
    public function testPluginInitIgnoredIfPluginNotUninstalled(): void
    {
        $package = $this->createMock(PackageInterface::class);
        $package
            ->method('getName')
            ->willReturn('not/the-plugin');

        // Create an event for this package
        $event = $this->createConfiguredEventReturningPackage($package);

        // Ensure the unlink repository is not called on the executor
        $this->linkExecutor
            ->expects($this->never())
            ->method('linkRepository');

        $this->plugin->initPlugin($event);
    }

    /**
     * Tests that when the plugin is installed that it attempts to link all
     * packages found within any defined config.
     *
     * Useful if the user defines config prior to requiring the plugin in
     * their project.
     *
     * @return void
     */
    public function testPluginInitExecutedOnPluginInstall(): void
    {
        // Configure a mock repository and it's parent that can be accessed
        // by the mock event
        $repository = $this->createMock(InstalledRepositoryInterface::class);

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->method('getLocalRepository')
            ->willReturn($repository);

        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        $package = $this->createMock(PackageInterface::class);
        $package
            ->method('getName')
            ->willReturn('jparkinson1991/composer-linker-plugin');

        // Create an event for this package
        $event = $this->createConfiguredEventReturningPackage($package);

        $event
            ->method('getComposer')
            ->willReturn($composer);

        // Ensure the event has a mocked io for output
        $event
            ->method('getIO')
            ->willReturn($this->createMock(IOInterface::class));

        // Ensure the unlink repository is not called on the executor
        $this->linkExecutor
            ->expects($this->once())
            ->method('linkRepository');

        $this->plugin->initPlugin($event);
    }

    /**
     * Tests that exceptions throws during plugin initialisation do not beak
     * composer execution (ie breaks plugin install).
     *
     * Init plugin exceptions should be caught and logged, but should not stop
     * plugin install process.
     *
     * @return void
     */
    public function testPluginInitExceptionsDoNotBreakExecution(): void
    {
        $package = $this->createMock(PackageInterface::class);
        $package
            ->method('getName')
            ->willReturn('jparkinson1991/composer-linker-plugin');

        // Create an event for this package
        $event = $this->createConfiguredEventReturningPackage($package);

        // Mock a usable composer instance for use with the event
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->method('getLocalRepository')
            ->willReturn($this->createMock(InstalledRepositoryInterface::class));

        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        $event
            ->method('getComposer')
            ->willReturn($composer);

        // Create mock io, expect at least one error write
        $io = $this->createMock(IOInterface::class);
        $io
            ->expects($this->atLeastOnce())
            ->method('writeError');

        $event
            ->method('getIO')
            ->willReturn($io);

        // Create an exception collection, add a few exceptions to it
        $exceptionCollection = new LinkExecutorExceptionCollection();
        $exceptionCollection->addException($this->createMock(LinkExecutorException::class));
        $exceptionCollection->addException($this->createMock(LinkExecutorException::class));
        $exceptionCollection->addException($this->createMock(LinkExecutorException::class));

        $this->linkExecutor
            ->method('linkRepository')
            ->willThrowException($exceptionCollection);

        try {
            $this->plugin->initPlugin($event);
        }
        catch (Exception $e) {
            // Trigger an assertation error if exception was thrown
            $this->assertFalse(true, 'Exception thrown during plugin init');
        }
    }

    /**
     * Tests that plugin cleanup is not executed if a package is uninstalled
     * that is not this plugin.
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function testPluginCleanupIgnoredIfPluginNotUninstalled(): void
    {
        $package = $this->createMock(PackageInterface::class);
        $package
            ->method('getName')
            ->willReturn('not/the-plugin');

        // Create an event for this package
        $event = $this->createConfiguredEventReturningPackage($package);

        // Ensure the unlink repository is not called on the executor
        $this->linkExecutor
            ->expects($this->never())
            ->method('unlinkRepository');

        $this->plugin->cleanUpPlugin($event);
    }

    /**
     * Tests that if the plugin is uninstalled it will clean up after itself
     * removing all previously linked package files.
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function testPluginCleanupExecutedOnPluginUninstall(): void
    {
        // Configure a mock repository and it's parent that can be accessed
        // by the mock event
        $repository = $this->createMock(InstalledRepositoryInterface::class);

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->method('getLocalRepository')
            ->willReturn($repository);

        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        $package = $this->createMock(PackageInterface::class);
        $package
            ->method('getName')
            ->willReturn('jparkinson1991/composer-linker-plugin');

        // Create an event for this package
        $event = $this->createConfiguredEventReturningPackage($package);

        $event
            ->method('getComposer')
            ->willReturn($composer);

        // Ensure the event has a mocked io for output
        $event
            ->method('getIO')
            ->willReturn($this->createMock(IOInterface::class));

        // Ensure the unlink repository is not called on the executor
        $this->linkExecutor
            ->expects($this->once())
            ->method('unlinkRepository');

        $this->plugin->cleanUpPlugin($event);
    }

    /**
     * Tests that any exceptions thrown during plugin cleanup are caught
     * and do not break execution of the cleanup process
     *
     * @return void
     */
    public function testPluginCleanupExceptionsDoNotBreakExecution(): void
    {
        $package = $this->createMock(PackageInterface::class);
        $package
            ->method('getName')
            ->willReturn('jparkinson1991/composer-linker-plugin');

        // Create an event for this package
        $event = $this->createConfiguredEventReturningPackage($package);

        // Mock a usable composer instance for use with the event
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->method('getLocalRepository')
            ->willReturn($this->createMock(InstalledRepositoryInterface::class));

        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        $event
            ->method('getComposer')
            ->willReturn($composer);

        // Create mock io, expect at least one error write
        $io = $this->createMock(IOInterface::class);
        $io
            ->expects($this->atLeastOnce())
            ->method('writeError');

        $event
            ->method('getIO')
            ->willReturn($io);

        // Create an exception collection, add a few exceptions to it
        $exceptionCollection = new LinkExecutorExceptionCollection();
        $exceptionCollection->addException($this->createMock(LinkExecutorException::class));
        $exceptionCollection->addException($this->createMock(LinkExecutorException::class));
        $exceptionCollection->addException($this->createMock(LinkExecutorException::class));

        $this->linkExecutor
            ->method('unlinkRepository')
            ->willThrowException($exceptionCollection);

        try {
            $this->plugin->cleanUpPlugin($event);
        }
        catch (Exception $e) {
            // Trigger an assertation error if exception was thrown
            $this->assertFalse(true, 'Exception thrown during plugin cleanup');
        }
    }

    /**
     * Creates a package event from the passed package object and configures
     * the package extractor property (thus service within the plugin) to
     * return the known package from the event when it's encountered as a
     * method parameter
     *
     * @param PackageInterface $package
     *     The package to configure within the event
     *
     * @return \Composer\Installer\PackageEvent|\PHPUnit\Framework\MockObject\MockObject
     *     The mocked, plugin handled event
     */
    protected function createConfiguredEventReturningPackage(PackageInterface $package): MockObject
    {
        // Mock an event class
        $event = $this->createMock(PackageEvent::class);

        // Configure the package extractor used inside the plugin to
        // return the known package when receiving the created event
        $this->packageExtractor
            ->method('extractFromEvent')
            ->with($event)
            ->willReturn($package);

        return $event;
    }
}
