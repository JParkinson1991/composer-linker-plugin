<?php
/**
 * @file
 * LinkFileHandlerTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Link;

use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Exception;
use InvalidArgumentException;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandler;
use JParkinson1991\ComposerLinkerPlugin\Tests\Support\ReflectionMutatorTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class LinkFileHandlerTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Link
 */
class LinkFileHandlerTest extends TestCase
{
    /**
     * Use reflection mutator to bypass visibility
     */
    use ReflectionMutatorTrait;

    /**
     * Accessible meta for the test package created as part of link
     * definition instance creation.
     *
     * @see createLinkDefinition()
     */
    public const TEST_PACKAGE_NAME = 'test/package';
    public const TEST_PACKAGE_INSTALL_DIR = '/vendor/test/package';

    /**
     * The mocked file system instance
     *
     * @var \PHPUnit\Framework\MockObject\MockObject|\Symfony\Component\Filesystem\Filesystem
     */
    protected $fileSystem;

    /**
     * The mocked installation managed
     *
     * @var \Composer\Installer\InstallationManager|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $installationManager;

    /**
     * The link file handler to test
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandler
     */
    protected $linkFileHandler;

    /***
     * Initialise the test class prior to each test case being ran
     */
    public function setup(): void
    {
        // Leverage use of php pass object by reference
        // Store link file handler mock dependencies against this test class
        // Change test class property behaviour effects mock dependency
        // behaviour inside of the LinkFileHandler class
        // Do not mock composer file system, use it as is to ensure path
        // creation working as expected
        $this->fileSystem = $this->createMock(Filesystem::class);
        $this->installationManager = $this->createMock(InstallationManager::class);
        $this->linkFileHandler = new LinkFileHandler(
            $this->fileSystem,
            new \Composer\Util\Filesystem(),
            $this->installationManager
        );
    }

    /**
     * Test that by default the current working directory is used for the
     * root path of the link file handler.
     */
    public function testRootPathToCurrentWorkingDirOnCreate(): void
    {
        $this->assertSame(
            realpath(getcwd()),
            $this->getPropertyValue($this->linkFileHandler, 'rootPath')
        );
    }

    /**
     * Tests that the root path can be set against the object
     */
    public function testRootPathCanBeSet(): void
    {
        $this->linkFileHandler->setRootPath(__DIR__);
        $this->assertSame(
            __DIR__,
            $this->getPropertyValue($this->linkFileHandler, 'rootPath')
        );
    }

    /**
     * Tests that an invalid argument exception is throw when setting the root
     * path if the given path does not exist
     */
    public function testRootPathExceptionOnFileNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->linkFileHandler->setRootPath(__DIR__.'/test-file-does-not-exist');
    }

    /**
     * Tests tat when trying to set a path to a file as the root path an
     * exception is thrown
     */
    public function testRootPathExceptionOnNotADirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->linkFileHandler->setRootPath(__FILE__);
    }
    /**
     * Tests that when a given a link definition to link a full directory
     * it uses it's internal services to attempt to link the full directory.
     *
     * @dataProvider linkDirectoryProvider
     *
     * @param string $inputDestDir
     *     The destination dir for the created link definition instance
     * @param bool $useCopy
     *     Link definition uses copy?
     * @param string $expectedMethod
     *     The name of the expected file system method to be called
     * @param string $expectedDestDir
     *     The expected destination dir to be passed to file system
     * @param string|null $rootPath
     *     Optional root path to set against the link file handler
     *
     * @throws \Exception
     */
    public function testItLinksADirectory(
        string $inputDestDir,
        bool $useCopy,
        string $expectedMethod,
        string $expectedDestDir,
        string $rootPath = null
    ): void {
        // Create the link definition
        // Containing a testable package
        $linkDefinition = $this->createLinkDefinition($inputDestDir);
        $linkDefinition->setCopyFiles($useCopy);

        // Set the file handler root path if needed
        // Use property injection rather than setter to bypass errors during
        // test
        if ($rootPath !== null) {
            $this->setPropertyValue(
                $this->linkFileHandler,
                'rootPath',
                $rootPath
            );
        }

        // Assert the exception on which method called with which values
        // In this instance the symlink method with the destination dir and
        // the test package install dir
        $this->fileSystem
            ->expects($this->once())
            ->method($expectedMethod)
            ->with(self::TEST_PACKAGE_INSTALL_DIR, $expectedDestDir);

        $this->linkFileHandler->link($linkDefinition);
    }

    /**
     * Provides test case data for the testItLinksADirectory test case.
     *
     * phpcs:disable Generic.Files.LineLength.TooLong
     *
     * @return array|array[]
     */
    public function linkDirectoryProvider(): array
    {
        return [
            // Input destination, uses copy?, expected method, expected destination dir, set root path (optional)
            'absolute symlink' => ['/destination/dir', false, 'symlink', '/destination/dir'],
            'absolute copy' => ['/destination/dir', true, 'mirror', '/destination/dir'],
            'relative symlink' => ['relative/dir', false, 'symlink', realpath(getcwd()).'/relative/dir'],
            'relative copy' => ['relative/dir', true, 'mirror', realpath(getcwd()).'/relative/dir'],
            'set root path absolute symlink' => ['/destination/dir', false, 'symlink', '/destination/dir', __DIR__],
            'set root path absolute copy' => ['/destination/dir', true, 'mirror', '/destination/dir', __DIR__],
            'set root path relative symlink' => ['relative/dir', false, 'symlink', __DIR__.'/relative/dir', __DIR__],
            'set root path relative copy' => ['relative/dir', true, 'mirror', __DIR__.'/relative/dir', __DIR__],
            'relative copy dot' => ['./relative/dir', true, 'mirror', '/root/dir/relative/dir', '/root/dir'],
            'relative copy parent' => ['../relative/dir', true, 'mirror', '/root/relative/dir', '/root/dir'],
            'relative copy dot parents' => ['./../../relative/dir', true, 'mirror', '/root/relative/dir', '/root/dir/another'],
            'relative symlink dot' => ['./relative/dir', false, 'symlink', '/root/dir/relative/dir', '/root/dir'],
            'relative symlink parent' => ['../relative/dir', false, 'symlink', '/root/relative/dir', '/root/dir'],
            'relative symlink dot parents' => ['./../../relative/dir', false, 'symlink', '/root/relative/dir', '/root/dir/another']
        ];
    }

    /**
     * Tests that during directory linking any thrown exceptions are caught
     * and logged before bubbling
     */
    public function testItInterceptsAndLogsExceptionsDuringLinking()
    {
        $linkDefinition = $this->createLinkDefinition('/destination/dir');

        // Ensure symlink method throws exception
        $this->fileSystem
            ->method('symlink')
            ->willThrowException(new \Symfony\Component\Filesystem\Exception\InvalidArgumentException());

        // Prepare a callback function to determine how many times the logger
        // was used to write an error. This is needed as psr log interface
        // allows errors to be logged via two methods:
        // error() and log(LogLevel::Error)
        // We abuse the fact callbacks can be used for dynamic argument values
        // and simple increment the log count whilst return true which informs
        // phpstorm that the passed method parameter is valid
        $loggerCalledCount = 0;
        $loggerCallback = static function ($methodParameter) use (&$loggerCalledCount) {
            $loggerCalledCount++;

            return true;
        };

        // Create a mock logger that can be observed
        // As stated above psr log can trigger errors via two methods
        // Mock them here, incrementing the logger called counter when
        // executed.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')->with($this->callback($loggerCallback));
        $logger->method('log')->with(LogLevel::ERROR, $this->callback($loggerCallback));

        // Set observed logger against the file handler
        $this->linkFileHandler->setLogger($logger);

        // Can not use the ->expectException method here
        // Due to phpunit's handling of expected exceptions, assertations
        // required to be run after caught exception will not be executed
        // thus we cannot check whether the log was actually called.
        // Catch the exception manually so it can be asserted against as with
        // the logger method calls
        $exceptionCaught = false;
        try {
            $this->linkFileHandler->link($linkDefinition);
        }
        catch (\Symfony\Component\Filesystem\Exception\InvalidArgumentException $e) {
            $exceptionCaught = true;
        }

        $this->assertTrue($exceptionCaught, 'Did not catch link exception');
        $this->assertGreaterThan(0, $loggerCalledCount);
    }

    /**
     * Tests that when a given a link definition that linked a full directory
     * the link file handler uses it's internal services to delete that
     * entire directory
     *
     * @dataProvider unlinkDirectoryProvider
     *
     * @param string $inputDestDir
     *     The destination dir for the created link file handler
     * @param string $expectedDeleteDir
     *     The directory expected to be passed to the delete path
     */
    public function testItUnlinksADirectory(string $inputDestDir, string $expectedDeleteDir): void
    {
        $linkDefinition = $this->createLinkDefinition($inputDestDir);

        $this->fileSystem
            ->expects($this->once())
            ->method('remove')
            ->with($expectedDeleteDir);

        $this->linkFileHandler->unlink($linkDefinition);
    }

    /**
     * Provides test data for directory unlinking
     *
     * @return array
     */
    public function unlinkDirectoryProvider(): array
    {
        return [
            // input destination dir, expected delete dir
            'relative path' => ['destination/dir', realpath(getcwd()).'/destination/dir'],
            'absolute path' => ['/absolute/dir', '/absolute/dir']
        ];
    }

    /**
     * Tests that if an exception is thrown during the unlinking process it
     * is intercepted and logged before bubbling up.
     *
     * Detailed information on test case structure exists in the linking
     * exception interception test case.
     *
     * @see testItInterceptsAndLogsExceptionsDuringLinking
     */
    public function testItInterceptsAndLogsExceptionsDuringUnlink()
    {
        $linkDefinition = $this->createLinkDefinition('/destination/dir');

        // Ensure remove method throws exception
        $this->fileSystem
            ->method('remove')
            ->willThrowException(new Exception());

        $loggerCalledCount = 0;
        $loggerCallback = static function ($methodParameter) use (&$loggerCalledCount) {
            $loggerCalledCount++;

            return true;
        };
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')->with($this->callback($loggerCallback));
        $logger->method('log')->with(LogLevel::ERROR, $this->callback($loggerCallback));
        $this->linkFileHandler->setLogger($logger);

        $exceptionCaught = false;
        try {
            $this->linkFileHandler->unlink($linkDefinition);
        }
        catch (Exception $e) {
            $exceptionCaught = true;
        }

        $this->assertTrue($exceptionCaught, 'Did not catch unlink exception');
        $this->assertGreaterThan(0, $loggerCalledCount);
    }

    /**
     * Instantiates a mock aware LinkDefinition instance.
     *
     * This method creates a link definition with a mock package with known
     * details. It will also configure the mock installation manager to return
     * a known package installation path when receiving the package injected
     * into the link definition.
     *
     * @param string $destinationDir
     *     The destination dir for the linked package
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition
     */
    protected function createLinkDefinition(string $destinationDir): LinkDefinition
    {
        // Create the test package with a known name
        $package = $this->createMock(PackageInterface::class);
        $package
            ->method('getName')
            ->willReturn(self::TEST_PACKAGE_NAME);

        // Have the file system return true on absolute path calls if first
        // character of dest dir is '/', allows for simple test
        if (strpos($destinationDir, '/') === 0) {
            $this->fileSystem
                ->method('isAbsolutePath')
                ->with($destinationDir)
                ->willReturn(true);
        }

        // Have the installation manager return a known path when dealing with
        // this package
        $this->installationManager
            ->method('getInstallPath')
            ->with($package)
            ->willReturn(self::TEST_PACKAGE_INSTALL_DIR);

        return new LinkDefinition($package, $destinationDir);
    }
}
