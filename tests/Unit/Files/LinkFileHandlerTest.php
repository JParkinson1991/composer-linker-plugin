<?php
/**
 * @file
 * LinkFileHandlerTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Files;

use Composer\Installer\InstallationManager;
use Composer\Package\Package;
use JParkinson1991\ComposerLinkerPlugin\Config\LinkConfig;
use JParkinson1991\ComposerLinkerPlugin\Config\LinkConfigInterface;
use JParkinson1991\ComposerLinkerPlugin\Files\LinkFileHandler;
use JParkinson1991\ComposerLinkerPlugin\Files\LinkFileHandlerUnsupportedPackageException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class LinkFileHandlerTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Files
 */
class LinkFileHandlerTest extends TestCase
{
    /**
     * Testable file handler instance
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Files\LinkFileHandler
     */
    protected $fileHandler;

    /**
     * Sets up this class prior to running each test case
     */
    public function setUp(): void
    {
        $fileSystem = $this->createMock(Filesystem::class);
        $installationManager = $this->createMock(InstallationManager::class);
        $this->fileHandler = new LinkFileHandler($fileSystem, $installationManager);
    }

    /**
     * Tests the default root path is set as expected
     */
    public function testRootPathSettings()
    {
        $rootPathProperty = new \ReflectionProperty(LinkFileHandler::class, 'rootPath');
        $rootPathProperty->setAccessible(true);

        // On instantation root path should be set to the current working dir
        // Test this is the case by accessing the protected property value
        $this->assertSame(realpath(getcwd()), $rootPathProperty->getValue($this->fileHandler));

        // Change the current working dir, re-setup the class
        // (ie reinstantiate the link file handler) and check the root path was
        // set to the new working directory
        chdir(__DIR__);
        $this->setUp();
        $this->assertSame(__DIR__, $rootPathProperty->getValue($this->fileHandler));
    }

    /**
     * Tests that package link configs can be added to the file handler
     */
    public function testPackageLinkConfigsCanBeAdded()
    {
        // Ensure the links configs property can be accessed
        $linkConfigsProperty = new \ReflectionProperty(LinkFileHandler::class, 'linkConfigs');
        $linkConfigsProperty->setAccessible(true);

        // Add first config
        $linkConfig = $this->createMock(LinkConfigInterface::class);
        $this->fileHandler->addConfig($linkConfig);

        $this->assertCount(1, $linkConfigsProperty->getValue($this->fileHandler));

        // Create a new config
        unset($linkConfig);
        $linkConfig = $this->createMock(LinkConfigInterface::class);
        $this->fileHandler->addConfig($linkConfig);

        $this->assertCount(2, $linkConfigsProperty->getValue($this->fileHandler));
    }

    /**
     * Ensures the same package config instances can not be added to the
     */
    public function testPackageConfigsCanNotBeAddedMultipleTimes()
    {
        // Ensure the links configs property can be accessed
        $linkConfigsProperty = new \ReflectionProperty(LinkFileHandler::class, 'linkConfigs');
        $linkConfigsProperty->setAccessible(true);

        // Add the same config twice
        $linkConfig = $this->createMock(LinkConfigInterface::class);
        $this->fileHandler->addConfig($linkConfig);
        $this->fileHandler->addConfig($linkConfig);

        // Only one object should be set against the file handler
        $this->assertCount(1, $linkConfigsProperty->getValue($this->fileHandler));

        // Create a new config and add that
        unset($linkConfig);
        $linkConfig = $this->createMock(LinkConfigInterface::class);
        $this->fileHandler->addConfig($linkConfig);

        // Both configs should be set against file handler
        $this->assertCount(2, $linkConfigsProperty->getValue($this->fileHandler));
    }

    /**
     * Test that package supports can be checked properly
     */
    public function testPackageSupportCanBeChecked()
    {
        $package = new Package('test/package', '1.0.0', '1');

        // In its default state no package should be support because no configs exists
        $this->assertNotTrue($this->fileHandler->supportsPackage($package));

        // Add a config for the package and test it is not supported
        $this->fileHandler->addConfig(new LinkConfig('test/package', '/dest/dir'));
        $this->assertTrue($this->fileHandler->supportsPackage($package));

        // Create a new package that shouldnt be supported and check this is the case
        $newPackage = new Package('new/package', '1.0.0', '1');
        $this->assertNotTrue($this->fileHandler->supportsPackage($newPackage));
    }

    /**
     * Tests that the link method throws an exception when passed an
     * unsupported package
     */
    public function testLinkThrowsExceptionOnUnsupportedPackage()
    {
        $package = new Package('test/package', '1.0.0', '1');

        $this->expectException(LinkFileHandlerUnsupportedPackageException::class);
        $this->fileHandler->link($package);
    }

    /**
     * Tests the class links packages as expected using it's internal services
     *
     * Test that, the installation path is used and the expected destination
     * directory/settings etc is used from the configs.
     *
     * This test does not use simple mocks provided by class properties,
     * dependent service observers etc are required for testing
     */
    public function testItLinksAPackage()
    {
        // This is the package we will test against
        $package = new Package('test/package', '1.0.0', 1);

        // Prepare the installation manager mock to return a known install
        // path for the test package, can be used to test file system calls
        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager
            ->expects($this->once())
            ->method('getInstallPath')
            ->with($package)
            ->willReturn('/vendor/test/package');

        // Create a link config that supports the test package with an absolute
        // destination path
        $supportedLinkConfig = new LinkConfig('test/package', '/app/destination/dir');

        // Create an unsupported link config so we can test it is ignored during
        // linking
        $unsupportedLinkConfig = new LinkConfig('unsupported/package', '/');

        // Prepare the file system mock, given only one supported package has
        // been created we know the symlink method should be called only once
        // and we know what the source and destination directories should be
        $fileSystem = $this->createPartialMock(Filesystem::class, ['symlink']);
        $fileSystem
            ->expects($this->once())
            ->method('symlink')
            ->with(
                '/vendor/test/package',
                '/app/destination/dir'
            );

        // Instantiate the file handler, adding both the supported and
        // unsupported configs, test behaviour when it used to link the
        // test package
        $linkFileHandler = new LinkFileHandler($fileSystem, $installationManager);
        $linkFileHandler->addConfig($supportedLinkConfig);
        $linkFileHandler->addConfig($unsupportedLinkConfig);
        $linkFileHandler->link($package);
    }

    /**
     * Tests that file processing errors can be skippable during package
     * link process.
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Files\LinkFileHandlerUnsupportedPackageException
     */
    public function testThatLinkErrorsAreSkipable()
    {
        // This is the package we will test against
        $package = new Package('test/package', '1.0.0', 1);

        // Create a link config that supports the test package
        $linkConfig = new LinkConfig('test/package', '/app/destination/dir');

        // Mock the installation manager so it returns a proper install path
        // for the test package
        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager
            ->method('getInstallPath')
            ->with($package)
            ->willReturn('/vendor/test/package');

        // Mock the filesystem service so that the symlink method always
        // triggers an exception, one that should be skipable
        $fileSystem = $this->createPartialMock(Filesystem::class, ['symlink']);
        $fileSystem
            ->method('symlink')
            ->willThrowException(new \Exception());

        // Initialise the file handler, add the config
        $linkFileHandler = new LinkFileHandler($fileSystem, $installationManager);
        $linkFileHandler->addConfig($linkConfig);

        // Link the package, with errors skipped
        // These should not trigger exceptions
        $linkFileHandler->link($package);
        $linkFileHandler->link($package, true);

        // Add the excetpion expectation, and dont skip errors on link.
        $this->expectException(\Exception::class);
        $linkFileHandler->link($package, false);
    }

    /**
     * Tests that the class unlinks packages as expected using internal services
     *
     */
    public function testItUnlinksAPackage()
    {
        // Create a test package for unlink
        $package = new Package('test/package', '1.0.0', 1);

        // Create a supported and unsupproted link config
        // Allows to test that file linker class only uses the supported configs
        $supportedConfig = new LinkConfig('test/package', '/app/destination/dir');
        $unsupportedConfig = new LinkConfig('unsupported/package', '/dest/dir');

        // Create a partial file system mock
        // Implement observation on the remove method
        $fileSystem = $this->createPartialMock(Filesystem::class, ['remove']);
        $fileSystem
            ->expects($this->once())
            ->method('remove')
            ->with(
                '/app/destination/dir'
            );

        $linkFileHandler = new LinkFileHandler($fileSystem, $this->createMock(InstallationManager::class));
        $linkFileHandler->addConfig($supportedConfig);
        $linkFileHandler->addConfig($unsupportedConfig);
        $linkFileHandler->unlink($package);
    }

    public function testThatUnlinkErrorsAreSkipable()
    {
        // This is the package we will test against
        $package = new Package('test/package', '1.0.0', 1);

        // Create a link config that supports the test package
        $linkConfig = new LinkConfig('test/package', '/app/destination/dir');

        // Mock the filesystem service so that the symlink method always
        // triggers an exception, one that should be skipable
        $fileSystem = $this->createPartialMock(Filesystem::class, ['remove']);
        $fileSystem
            ->method('remove')
            ->willThrowException(new \Exception());

        // Initialise the file handler, add the config
        $linkFileHandler = new LinkFileHandler($fileSystem, $this->createMock(InstallationManager::class));
        $linkFileHandler->addConfig($linkConfig);

        // Link the package, with errors skipped
        // These should not trigger exceptions
        $linkFileHandler->unlink($package);
        $linkFileHandler->unlink($package, true);

        // Add the excetpion expectation, and dont skip errors on link.
        $this->expectException(\Exception::class);
        $linkFileHandler->unlink($package, false);
    }

}
