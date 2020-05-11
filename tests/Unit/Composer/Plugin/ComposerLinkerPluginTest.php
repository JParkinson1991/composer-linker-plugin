<?php
/**
 * @file
 * ComposerLinkerPluginTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Plugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\RootPackageInterface;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractor;
use JParkinson1991\ComposerLinkerPlugin\Composer\Plugin\ComposerLinkerPlugin;
use JParkinson1991\ComposerLinkerPlugin\Files\LinkFileHandler;
use PHPUnit\Framework\TestCase;

/**
 * Class ComposerLinkerPluginTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Plugin
 */
class ComposerLinkerPluginTest extends TestCase
{
    /**
     * Holds the IOInterface mock
     *
     * @var \PHPUnit\Framework\MockObject\MockObject|IOInterface
     */
    protected $io;

    /**
     * Holds the plugin instance
     *
     * @var ComposerLinkerPlugin
     */
    protected $plugin;

    /**
     * Sets up this test class prior to each test case being executed
     */
    public function setUp(): void
    {
        $this->io = $this->createMock(IOInterface::class);
        $this->plugin = new ComposerLinkerPlugin();
    }

    /**
     * Tests that the plugin is subscribed to the expected events
     */
    public function testSubscribedToExpectedEvents()
    {
        $this->assertInstanceOf(
            EventSubscriberInterface::class,
            $this->plugin
        );

        $subscribedEvents = $this->plugin::getSubscribedEvents();

        $this->assertCount(3, $subscribedEvents);
        $this->assertArrayHasKey(PackageEvents::POST_PACKAGE_INSTALL, $subscribedEvents);
        $this->assertArrayHasKey(PackageEvents::POST_PACKAGE_UPDATE, $subscribedEvents);
        $this->assertArrayHasKey(PackageEvents::POST_PACKAGE_UNINSTALL, $subscribedEvents);
    }

    /**
     * Tests that plugin aborts during activation if no config found in the
     * 'extra' section
     */
    public function testActivationIsAbortedIfConfigNotFound()
    {
        // Create composer mock with no 'extra' config
        $composer = $this->createComposerMockWithConfig([], false);

        $this->io->expects($this->once())
            ->method('writeError');

        $this->plugin->activate($composer, $this->io);

        $this->assertSame(false, $this->plugin->isActivated());
    }

    /**
     * Tests that plugin activation is aborted if a non array plugin config
     * definition is found in the 'extra' section of the root package,
     */
    public function testActivationAbortedOnFindinfNoArrayPluginConfig()
    {
        // Create composer with non array extra config for plugin
        $composer = $this->createComposerMockWithConfig('non-array');

        $this->io->expects($this->once())
            ->method('writeError');

        $this->plugin->activate($composer, $this->io);

        $this->assertSame(false, $this->plugin->isActivated());
    }

    /**
     * Tests that package definitions within the plugin config defined in the
     * 'extra' section of the composer.json file are processed as expected.
     *
     * Difficult to completely test due to lack of dependency injection in
     * composer plugins
     *
     * @dataProvider dataProviderPackageConfigs()
     *
     * @param array $packageConfigs
     *     The package configs as defined in 'extra'
     * @param int $expectedWriteErrorCalls
     *     The number of times these configs are expected to trigger errors
     *     It is assumed any that dont have been created successfully.
     */
    public function testProcessingOfPackageConfigsDuringActivation(
        array $packageConfigs,
        int $expectedWriteErrorCalls,
        bool $isPluginActivated
    ) {
        // Create composer with package configs
        $composer = $this->createComposerMockWithConfig($packageConfigs);

        $this->io->expects($this->exactly($expectedWriteErrorCalls))
            ->method('writeError');

        $this->plugin->activate($composer, $this->io);

        $this->assertSame($isPluginActivated, $this->plugin->isActivated());
    }

    /**
     * Tests that when the package events trigger a linking of a package
     * that execution is aborted if the plugin is not active.
     *
     * By default this plugin should not be activated, activation only occurs
     * after the activate method is called on it. If the linkPackageFromEvent
     * method is called on the plugin whilst it is in an unactivated state
     * no linking should be attempted for that package via internal
     * plugin services.
     */
    public function testLinkingAbortsWhenPluginNotActivated()
    {
        // Create a mock file handler that can be observed
        // Observe the link method is never called
        $fileHandler = $this->createMock(LinkFileHandler::class);
        $fileHandler
            ->expects($this->never())
            ->method('link');

        // Inject the file handler directly onto plugin's property
        // No dependency injection in composer plugin so this is the only
        // option
        $this->setPluginProperty('fileHandler', $fileHandler);

        // Assert the plugin is not activate by default
        // $this->plugin is an instantiated plugin object, but it has not had
        // the activate method called on it
        $this->assertNotTrue($this->plugin->isActivated());

        // Run the link method, the file handler service should not have
        // linked called on it
        $this->plugin->linkPackageFromEvent($this->createMock(PackageEvent::class));
    }

    /**
     * Tests that when the plugin handles a package event for an unsupported
     * package it does not attempt to link it.
     *
     * This plugin requires package specific configuration to be defined so
     * that it can configure it's service to properly handle project packages
     * during their lifecycle, ie. linking on install, removing on uninstall.
     * Before attempting any file handling operations the plugin should
     * determine whether the package extracted from a package event is
     * supported, that is, has config defined for it, so needs actioning. If the
     * package is not supported thus not to be actioned it is expected that no
     * 'linking' is triggered against the file handler service.
     */
    public function testLinkingAbortsWhenPackageNotSupported()
    {
        // Create simple package so it can be used in other service mocks
        $package = new Package('test/package', '1.0.0', '1');

        // Mock the event, so it can be used in other service mocks
        $event = $this->createMock(PackageEvent::class);

        // Create a mock package extractor, that will return a known package
        $packageExtractor = $this->createMock(PackageExtractor::class);
        $packageExtractor
            ->method('extractFromEvent')
            ->with($event)
            ->willReturn($package);

        // Create a mock file handler that can be observed
        // Ensure it returns that the test package is unsupported
        // Observe the link method is never called
        $fileHandler = $this->createMock(LinkFileHandler::class);
        $fileHandler
            ->method('supportsPackage')
            ->with($package)
            ->willReturn(false);
        $fileHandler
            ->expects($this->never())
            ->method('link');

        // Inject mocks directly into plugin properties
        // No dependency injection, only option
        $this->setPluginProperty('fileHandler', $fileHandler);
        $this->setPluginProperty('packageExtractor', $packageExtractor);

        // Force activation on plugin
        $this->setPluginProperty('isActivated', true);

        // Run the link method, testing the file handler doesnt attempt to link
        // the unsupported package
        $this->plugin->linkPackageFromEvent($event);
    }

    /**
     * Tests that on a package event containing a supporting package the link
     * processes is executed completely.
     *
     * On an activated plugin, receiving a supported package extracted form
     * a package event it is expected that the link handler within plugin
     * will executed the link method on the internal file handler service.
     */
    public function testLinkingExecutesOnSupportingPackage()
    {
        // Create a simple package for use in other service mocks
        $package = new Package('test/package', '1.0.0', '1');

        // Mock the event, so it can be used in other service mocks
        // Ensure io mock returned for any output triggered in link
        $event = $this->createMock(PackageEvent::class);
        $event
            ->method('getIo')
            ->willReturn($this->createMock(IOInterface::class));

        // Create a mock package extractor, that will return a known package
        $packageExtractor = $this->createMock(PackageExtractor::class);
        $packageExtractor
            ->method('extractFromEvent')
            ->with($event)
            ->willReturn($package);

        // Mock the file handler ensuring it returns true for support of the
        // simple package instantiated for this test. Add an observer that the
        // link method on the file handler is called with the package exactly
        // once. If the link method is called here we can assume the plugin
        // executed the link process fully and as expected
        $fileHandler = $this->createMock(LinkFileHandler::class);
        $fileHandler
            ->method('supportsPackage')
            ->with($package)
            ->willReturn(true);
        $fileHandler
            ->expects($this->once())
            ->method('link')
            ->with($package);

        // Inject mock services directly into plugin
        $this->setPluginProperty('fileHandler', $fileHandler);
        $this->setPluginProperty('packageExtractor', $packageExtractor);

        // Force activated state
        $this->setPluginProperty('isActivated', true);

        // Run the link method with the event, it shold trigger a call to
        // the file handler to link the package files
        $this->plugin->linkPackageFromEvent($event);
    }

    /**
     * Tests that the unlink process is not executed when the plugin is not
     * activated.
     *
     * Much the same as the link test, the plugin by default is not activated
     * ensure that no unlinking is handled/processed if the plugin has not
     * been activated by triggering it's activate method.
     */
    public function testUnlinkingAbortsWhenPluginNotActivated()
    {
        // Set up observer on file handler to ensure the unlink method is
        // never called on it
        $fileHandler = $this->createMock(LinkFileHandler::class);
        $fileHandler
            ->expects($this->never())
            ->method('unlink');

        // Inject the file handler directly onto plugin's property
        // No dependency injection in composer plugin so this is the only
        // option
        $this->setPluginProperty('fileHandler', $fileHandler);

        $this->plugin->unlinkPackageFromEvent($this->createMock(PackageEvent::class));
    }

    /**
     * Tests that unlinking processes is not executed on receiving an
     * unsupported package from a lifecycle event.
     *
     * During plugin activation, configuration is loaded containing details on
     * how to handle link/unlink a package's files. If no configuration was
     * found for the package extract from the lifecycle event no unlinking
     * should be attempted by the plugin's file handler service.
     */
    public function testUnlinkingAbortsOnSupportedPackage()
    {
        // Create a simple package that can be used in mocks of services
        $package = new Package('test/package', '1.0.0', '1');

        // Create a mock event, used to trigger the unlink process and mock
        // the package extractor returning the known simple package object
        $event = $this->createMock(PackageEvent::class);

        // Create a mock of the package extractor service so that when it
        // receives our mock event it returns the simple package we created
        $packageExtractor = $this->createMock(PackageExtractor::class);
        $packageExtractor
            ->method('extractFromEvent')
            ->with($event)
            ->willReturn($package);

        // Mock the file handler service ensuring it returns false on
        // supporting of the simple package created at the start of this test
        // case
        $fileHandler = $this->createMock(LinkFileHandler::class);
        $fileHandler
            ->method('supportsPackage')
            ->with($package)
            ->willReturn(false);

        // Unsupported packages should not trigger any unlinking on the internal
        // file handler service, create observer on that method expecting it
        // to never be called
        $fileHandler
            ->expects($this->never())
            ->method('unlink');

        // Inject the services into the plugin
        // No dependency injection in composer plugins so reflection needed
        $this->setPluginProperty('fileHandler', $fileHandler);
        $this->setPluginProperty('packageExtractor', $packageExtractor);

        // Ensure the plugin is activated, again, use reflection so activation
        // method does not need to be called
        $this->setPluginProperty('isActivated', true);

        $this->plugin->unlinkPackageFromEvent($event);
    }

    /**
     * Tests that unlink handling is executed fully for supported packages.
     *
     * Once the plugin has been activated and it receives a package extracted
     * from a package lifecycle event that it supports ie. has configuration
     * for it should pass this package to the internal file handling service
     * for unlinking.
     */
    public function testUnlinkingIsExecutedForSupportedPackages()
    {
        // Create the simple package for testing
        $package = new Package('test/package', '1.0.0', '1');

        // Mock the event that the plugin will handle
        // Allow IO to be pulled from event for plugin outputs
        $event = $this->createMock(PackageEvent::class);
        $event
            ->method('getIO')
            ->willReturn($this->createMock(IOInterface::class));

        // Mock the package extractor, returning our package from the
        // mocked event
        $packageExtractor = $this->createMock(PackageExtractor::class);
        $packageExtractor
            ->method('extractFromEvent')
            ->with($event)
            ->willReturn($package);

        // Mock the file handler, ensuring it supports the package created for
        // this test case
        $fileHandler = $this->createMock(LinkFileHandler::class);
        $fileHandler
            ->method('supportsPackage')
            ->with($package)
            ->willReturn(true);

        // Create observer on the file handler, expecting the unlink method to
        // be called on it once, with the simple package created in this test
        // case. If this method is called, it can be assumed that the plugin
        // handling the unlinking of the package to completion.
        $fileHandler
            ->expects($this->once())
            ->method('unlink')
            ->with($package);

        // Inject the mocked services into the plugin
        // No dependency injection available, use reflection
        $this->setPluginProperty('fileHandler', $fileHandler);
        $this->setPluginProperty('packageExtractor', $packageExtractor);

        // Force the plugin into an activated state to avoid early drop outs
        $this->setPluginProperty('isActivated', true);

        // Trigger the unlink handler on the plugin with the mock event
        $this->plugin->unlinkPackageFromEvent($event);
    }

    /**
     * Data provide for package configs
     *
     * Each record/data set holds the following 3 values
     * - An array of package config's as expected to be defined in 'extra'
     * - The number of times the writeError method should be executed
     *   Ie. the number of package configs that are invalid
     * - Whether the plugin was activated after processing these configs
     */
    public function dataProviderPackageConfigs()
    {
        return [
            // No configs, so no errors but plugin not activated
            [
                [],
                0,
                false
            ],
            // One config, non string index cause error dont activate
            [
                [
                    1 => 'destination/dir'
                ],
                1,
                false
            ],
            // One config, valid index, incorrect data, 1 error, plugin not activated
            [
                [
                    'package/name' => new \stdClass(),
                ],
                1,
                false
            ],
            // Two configs, one correct, one error, plugin activates
            [
                [
                    1 => 'destination/dir',
                    'package/name' => 'destination/dir'
                ],
                1,
                true
            ],
            // Two configs, both correct, no error, plugin activates
            [
                [
                    'first/package' => 'first/dir',
                    'second/package' => 'second/dir'
                ],
                0,
                true
            ]
        ];
    }

    /**
     * Creates a mock composer object configured to return a root package
     * containing the provided configuration array
     *
     * @param mixed $config
     *     The configuration value/values
     * @param bool $wrapped
     *     Whether to wrap the passed configuration array in the expected plugin
     *     config id.
     */
    protected function createComposerMockWithConfig($config, $wrapped = true)
    {
        if ($wrapped) {
            $config = [
                ComposerLinkerPlugin::PLUGIN_CONFIG_KEY => $config
            ];
        }

        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')
            ->willReturn($config);

        // Mock the composer object, retuning the configured root package
        // Also ensure that the installation manager is returned as expected
        // as it used via the plugin's activation method
        $composer = $this->createMock(Composer::class);
        $composer->method('getPackage')
            ->willReturn($rootPackage);
        $composer->method('getInstallationManager')
            ->willReturn($this->createMock(InstallationManager::class));


        return $composer;
    }

    /**
     * Centralises setting of plugin properties via reflection
     *
     * @param string $propertyName
     *     The name of the property to set
     * @param mixed $value
     *     The value to set against the plugin
     * @param \JParkinson1991\ComposerLinkerPlugin\Composer\Plugin\ComposerLinkerPlugin|null $instance
     *     The plugin instance that is having it's property value set
     *     If null, uses the $this->plugin property on this class
     */
    protected function setPluginProperty(string $propertyName, $value, ComposerLinkerPlugin $instance = null)
    {
        $property = new \ReflectionProperty(ComposerLinkerPlugin::class, $propertyName);
        $property->setAccessible(true);
        $property->setValue(
            $instance ?? $this->plugin,
            $value
        );
    }
}
