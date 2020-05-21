<?php
/**
 * @file
 * ComposerLinkerPluginTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Plugin;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\RootPackageInterface;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractor;
use JParkinson1991\ComposerLinkerPlugin\Composer\Plugin\ComposerLinkerPlugin;
use JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException;
use JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandler;
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
     * A mocked link definition factory used within the plugin property of
     * this class
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $linkDefinitionFactory;

    /**
     * A mocked link file handler used within the plugin property of this
     * class
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandler|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $linkFileHandler;

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

        // Inject a mocked link definition factory
        $linkDefinitionFactory = $this->createMock(LinkDefinitionFactory::class);
        $this->setPropertyValue($composerLinkerPlugin, 'linkDefinitionFactory', $linkDefinitionFactory);

        // Inject a mocked link file handler
        $linkFileHandler = $this->createMock(LinkFileHandler::class);
        $this->setPropertyValue($composerLinkerPlugin, 'linkFileHandler', $linkFileHandler);

        // Set to properties for access via each test case
        // Objects passed by reference
        // Altering class properties will alter plugin services
        $this->plugin = $composerLinkerPlugin;
        $this->packageExtractor = $packageExtractor;
        $this->linkDefinitionFactory = $linkDefinitionFactory;
        $this->linkFileHandler = $linkFileHandler;
    }

    /**
     * Tests that the plugin is subscribed to the expected events
     */
    public function testSubscribedToExpectedEvents(): void
    {
        $plugin = new ComposerLinkerPlugin();

        $this->assertInstanceOf(
            EventSubscriberInterface::class,
            $plugin
        );

        $subscribedEvents = $plugin::getSubscribedEvents();

        $this->assertCount(3, $subscribedEvents);
        $this->assertArrayHasKey(PackageEvents::POST_PACKAGE_INSTALL, $subscribedEvents);
        $this->assertArrayHasKey(PackageEvents::POST_PACKAGE_UPDATE, $subscribedEvents);
        $this->assertArrayHasKey(PackageEvents::POST_PACKAGE_UNINSTALL, $subscribedEvents);
    }

    /**
     * Test that the plugin will silently exist if no config found for the
     * package that trigger the post package install/update event.
     *
     * This is not an error, thus should not be treated as one
     */
    public function testPackageLinkingSilentlyExitsOnConfigNotFound(): void
    {
        // Create a test package, and a configured event that contains it
        $package = new Package('test/package', '1.0.0', '1');

        // Configure the definition factory to throw a config not found
        // exception when encountering our test package
        $this->linkDefinitionFactory->method('createForPackage')
            ->with($package)
            ->willThrowException(new ConfigNotFoundException());

        // Configure the link file handler service,
        // Link definition should throw a non breaking exception (ie it's caught)
        // Given exception is caught, ensure we dont actually try and link anything
        $this->linkFileHandler->expects($this->never())
            ->method('link');

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
     * Tests that the plugin exits and outputs an error message if package
     * extraction throws an exception
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

        // Ensure no linking occurs
        $this->linkFileHandler->expects($this->never())
            ->method('link');

        $this->plugin->linkPackageFromEvent($event);
    }

    /**
     * Tests that the plugin exists and outputs an error message if a package
     * config is deemed to be invalid
     */
    public function testPackageLinkingExitsWithOnInvalidConfigException(): void
    {
        $package = new Package('test/package', '1.0.0', '1');

        $this->linkDefinitionFactory
            ->method('createForPackage')
            ->with($package)
            ->willThrowException(new InvalidConfigException());

        // Mock an IO instance for observation, ensure it logs an error
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('writeError');

        $event = $this->createConfiguredEventReturningPackage($package);
        $event->method('getIO')->willReturn($io);

        // Ensure no linking occurs
        $this->linkFileHandler->expects($this->never())
            ->method('link');

        $this->plugin->linkPackageFromEvent($event);
    }

    /**
     * Tests that given no errors the plugin will actually attempt to link
     * a package's files.
     *
     * That is, when a package has associated config, it is extracted
     * successfully from the package event then it should be passed to the
     * file handler for linking.
     */
    public function testItRunsPackageLinking(): void
    {
        $this->linkFileHandler->expects($this->once())->method('link');

        $package = new Package('test/package', '1.0.0', '1');
        $event = $this->createConfiguredEventReturningPackage($package);
        $this->plugin->linkPackageFromEvent($event);
    }

    /**
     * Tests that during package unlinking (ie, when triggered by the package
     * uninstall event) that if no config is found for the given package then
     * the plugin will silently exit, no error, no exception, no log
     */
    public function testUnlinkingSilentlyExitsOnConfigNotFound(): void
    {
        // Create the test package and event
        $package = new Package('test/package', '1.0.0', '1');
        $event = $this->createConfiguredEventReturningPackage($package);

        // Have the link definition factory return not found exception
        $this->linkDefinitionFactory->method('createForPackage')
            ->with($package)
            ->willThrowException(new ConfigNotFoundException());

        // Mock an IO instance so logging/output can be monitored
        // Inject into event
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->never())->method($this->anything());
        $event->method('getIo')->willReturn($io);

        // Unlink should not be called for non found configs
        $this->linkFileHandler->expects($this->never())
            ->method('unlink');

        $this->plugin->unlinkPackageFromEvent($event);
    }

    /**
     * Tests that the plugin exits during unlinking if the package can not be
     * extracted from the given event.
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
        $this->linkFileHandler->expects($this->never())
            ->method('unlink');

        $this->plugin->unlinkPackageFromEvent($event);
    }

    /**
     * Tests that the plugin exists during unlinking if the package has
     * invalid config assocaited with it
     */
    public function testUnlinkExitsWithErrorOnInvalidConfig(): void
    {
        $package = new Package('test/package', '1.0.0', '1');

        $this->linkDefinitionFactory
            ->method('createForPackage')
            ->with($package)
            ->willThrowException(new InvalidConfigException());

        // Mock an IO instance for observation, ensure it logs an error
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('writeError');

        $event = $this->createConfiguredEventReturningPackage($package);
        $event->method('getIO')->willReturn($io);

        // Ensure no linking occurs
        $this->linkFileHandler->expects($this->never())
            ->method('unlink');

        $this->plugin->unlinkPackageFromEvent($event);
    }

    /**
     * That package unlinking is attempted when a handled package with
     * valid config is extracted from the uninstall event
     */
    public function testItRunsPackageUnlinking()
    {
        $this->linkFileHandler->expects($this->once())->method('unlink');

        $package = new Package('test/package', '1.0.0', '1');
        $event = $this->createConfiguredEventReturningPackage($package);
        $this->plugin->unlinkPackageFromEvent($event);
    }

    /**
     * Creates a package event from the passed package object and configures
     * the package extractor property (thus service within the plugin) to
     * return the known package from the event when it's encountered as a
     * method parameter
     *
     * @param Package $package
     *     The package to configure within the event
     *
     * @return \Composer\Installer\PackageEvent|\PHPUnit\Framework\MockObject\MockObject
     *     The mocked, plugin handled event
     */
    protected function createConfiguredEventReturningPackage(Package $package): MockObject
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
