<?php
/**
 * @file
 * LinkFileHandlerTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Link;

use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem as ComposerFilesystem;
use Exception;
use FilesystemIterator;
use InvalidArgumentException;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandler;
use JParkinson1991\ComposerLinkerPlugin\Tests\Support\ArrayTestTrait;
use JParkinson1991\ComposerLinkerPlugin\Tests\Support\ReflectionMutatorTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Filesystem\Exception\InvalidArgumentException as SymfonyFileSystemInvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * Class LinkFileHandlerTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Link
 */
class LinkFileHandlerTest extends TestCase
{
    /**
     * Extend array assertations
     */
    use ArrayTestTrait;

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
     * The composer file system instance
     *
     * @var \Composer\Util\Filesystem
     */
    protected $composerFileSystem;

    /**
     * The mocked file system instance
     *
     * @var \PHPUnit\Framework\MockObject\MockObject|\Symfony\Component\Filesystem\Filesystem
     */
    protected $symfonyFileSystem;

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
     *
     * @throws \Exception
     */
    public function setup(): void
    {
        // Leverage use of php pass object by reference
        // Store link file handler mock dependencies against this test class
        // Change test class property behaviour effects mock dependency
        // behaviour inside of the LinkFileHandler class
        // Do not mock composer file system, use it as is to ensure path
        // creation working as expected
        $this->symfonyFileSystem = $this->createMock(SymfonyFilesystem::class);
        $this->composerFileSystem = new ComposerFilesystem();
        $this->installationManager = $this->createMock(InstallationManager::class);
        $this->linkFileHandler = new LinkFileHandler(
            $this->symfonyFileSystem,
            $this->composerFileSystem,
            $this->installationManager
        );

        // Mock the file system so any paths starting '/' are treat as absolute
        $this->symfonyFileSystem
            ->method('isAbsolutePath')
            ->willReturnCallback(static function ($path) {
                return strpos($path, '/') === 0;
            });
    }

    /**
     * Tests that if the link file handler can not determine a root path
     * during construction an exception is thrown.
     *
     * @return void
     */
    public function testInstantiationFailsOnFailureToDetermineRootPath(): void
    {
        // Force realpath internal function to return false, ie non
        // determined
        /** @noinspection PhpParamsInspection */
        uopz_set_return('realpath', false);

        // Dont use expect exception here as it will stop resetting of
        // the realpath override
        try {
            new LinkFileHandler(
                $this->symfonyFileSystem,
                $this->composerFileSystem,
                $this->installationManager
            );

            $this->assertTrue(false, 'Exception was not thrown');
        }
        catch (Exception $e) {
            $this->assertTrue(true);
        }

        /** @noinspection PhpParamsInspection */
        uopz_unset_return('realpath');
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
     * @dataProvider dataProviderLinkDirectory
     *
     * @param string $inputDestDir
     *     The destination dir for the created link definition instance
     * @param bool $useCopy
     *     Link definition uses copy?
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
        $this->symfonyFileSystem
            ->expects($this->once())
            ->method(
                ($useCopy)
                    ? 'mirror'
                    : 'symlink'
            )
            ->with(self::TEST_PACKAGE_INSTALL_DIR, $expectedDestDir);

        $this->linkFileHandler->link($linkDefinition);
    }

    /**
     * Tests that the link file handler attempts to link specific files in a
     * link definition using it's internal services.
     *
     * The following test case ensures both linking and copying of specific
     * files as found within a link definition occurs (or is routed to the
     * file system) service as expected.
     *
     * @dataProvider dataProviderLinkFiles()
     *
     * @param string $rootPath
     *     The known root path to set against the file handler
     * @param string $inputDestDir
     *     The destination dir to set against the link config
     * @param array $fileMappings
     *     Array of file mappings to be set against the link definition where
     *     values in each subarray at:
     *         index 0 => source file
     *         index 1 => dest
     * @param array $expectedMappingsCallArguments
     *     Array of expected value pairings to be called on the file system
     *     service, where values in each subarray at
     *         index 0 => source file
     *         index 1 => dest write path
     *
     * @return void
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function testItLinksFiles(
        string $rootPath,
        string $inputDestDir,
        array $fileMappings,
        array $expectedMappingsCallArguments
    ): void {
        // Set the link file handlers root path to a known root path
        $this->setPropertyValue(
            $this->linkFileHandler,
            'rootPath',
            $rootPath
        );

        // Create the link definition using the passed input dest dir
        // Apply all of the provided file mappings to the link definition
        $linkDefinition = $this->createLinkDefinition($inputDestDir);
        foreach ($fileMappings as $fileMapping) {
            $linkDefinition->addFileMapping($fileMapping[0], $fileMapping[1]);
        }

        // Check both the copy and symlinking of files in the link definition
        $methodChecks = ['copy' => true, 'symlink' => false];
        foreach ($methodChecks as $methodName => $useCopy) {
            $linkDefinition->setCopyFiles($useCopy);

            // Prepare a mock on the expected called method to capture each
            // of the argument pairings passed to it. Store these values on
            // each method call and expect the exact number of calls as needed
            $actualMappingCallArguments = [];
            $this->symfonyFileSystem
                ->expects($this->exactly(count($expectedMappingsCallArguments)))
                ->method($methodName)
                ->willReturnCallback(static function ($source, $destination) use (&$actualMappingCallArguments) {
                    $actualMappingCallArguments[] = [$source, $destination];
                });

            // Link the files within the link definition
            $this->linkFileHandler->link($linkDefinition);

            // Check that the argument pairings that were actually called
            // match was expected to be called
            $this->assertArraySame($expectedMappingsCallArguments, $actualMappingCallArguments);
        }
    }

    /**
     * Tests that during linking any thrown exceptions are caught
     * and logged before bubbling.
     *
     * @dataProvider dataProviderLinkExceptionInterception
     *
     * @param bool $useCopy
     *     Should the link definition use copy
     * @param array $fileMappings
     *     File mappings to set against the link definition
     *     source => dest
     *
     * @return void
     *
     * @throws \Exception
     * @noinspection PhpUnusedParameterInspection
     */
    public function testItInterceptsAndLogsExceptionsDuringLinking(bool $useCopy, array $fileMappings = []): void
    {
        $linkDefinition = $this->createLinkDefinition('/destination/dir');
        $linkDefinition->setCopyFiles($useCopy);
        foreach ($fileMappings as $source => $dest) {
            $linkDefinition->addFileMapping($source, $dest);
        }

        // Ensure all the filesystem methods will throw exceptions
        $this->symfonyFileSystem
            ->method('mirror')
            ->willThrowException(new SymfonyFileSystemInvalidArgumentException());
        $this->symfonyFileSystem
            ->method('symlink')
            ->willThrowException(new SymfonyFileSystemInvalidArgumentException());
        $this->symfonyFileSystem
            ->method('copy')
            ->willThrowException(new SymfonyFileSystemInvalidArgumentException());

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

        // Set use copy value against the link definition

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
        catch (SymfonyFileSystemInvalidArgumentException $e) {
            $exceptionCaught = true;
        }

        // Check exception was caught, check it was logged
        $this->assertTrue($exceptionCaught, 'Did not catch link exception for directory linking');
        $this->assertGreaterThan(0, $loggerCalledCount);
    }

    /**
     * Tests that when a given a link definition that linked a full directory
     * the link file handler uses it's internal services to delete that
     * entire directory
     *
     * @dataProvider dataProviderUnlinkDirectory
     *
     * @param string $inputDestDir
     *     The destination dir for the created link file handler
     * @param string $expectedDeleteDir
     *     The directory expected to be passed to the delete path
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItUnlinksADirectory(string $inputDestDir, string $expectedDeleteDir): void
    {
        $linkDefinition = $this->createLinkDefinition($inputDestDir);

        $this->symfonyFileSystem
            ->expects($this->once())
            ->method('remove')
            ->with($expectedDeleteDir);

        $this->linkFileHandler->unlink($linkDefinition);
    }

    /**
     * Tests that when a given a link definition that linked a set of specific
     * files the link file handler uses it's internal file system service
     * and attempts to delete the files at the expected locations
     *
     * @return void
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function testItUnlinksFiles(): void
    {
        $this->setPropertyValue(
            $this->linkFileHandler,
            'rootPath',
            '/root/path'
        );

        $linkDefinition = $this->createLinkDefinition('dest/dir');
        $linkDefinition->addFileMapping('source.txt', 'relative.txt');
        $linkDefinition->addFileMapping('source.txt', '/absolute.txt');

        // Ensure the remove method is called with the expected deletion paths
        // Ie, is absolute treated as such and are relative paths resolved
        // correctly.
        $this->symfonyFileSystem
            ->expects($this->exactly(2))
            ->method('remove')
            ->with($this->logicalOr(
                $this->equalTo('/root/path/dest/dir/relative.txt'),
                $this->equalTo('/absolute.txt')
            ));

        $this->linkFileHandler->unlink($linkDefinition);
    }

    /**
     * Tests that if an exception is thrown during the unlinking process it
     * is intercepted and logged before bubbling up.
     *
     * Detailed information on test case structure exists in the linking
     * exception interception test case.
     *
     * @see testItInterceptsAndLogsExceptionsDuringLinking
     *
     * @return void
     * @noinspection PhpUnusedParameterInspection
     */
    public function testItInterceptsAndLogsExceptionsDuringUnlink(): void
    {
        $linkDefinition = $this->createLinkDefinition('/destination/dir');

        // Ensure remove method throws exception
        $this->symfonyFileSystem
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

        $this->assertTrue($exceptionCaught, 'Did not catch unlink exception when unlinking directory');
        $this->assertGreaterThan(0, $loggerCalledCount);

        // Add a file mapping to the link definition to ensure unlinking of
        // specific files also have their exceptions interceptred and logged
        $linkDefinition->addFileMapping('source.txt', 'dest.txt');

        // Reset the log counter
        $loggerCalledCount = 0;

        // Retest for files
        $exceptionCaught = false;
        try {
            $this->linkFileHandler->unlink($linkDefinition);
        }
        catch (Exception $e) {
            $exceptionCaught = true;
        }

        $this->assertTrue($exceptionCaught, 'Did not catch unlink exception when unlinking specific files');
        $this->assertGreaterThan(0, $loggerCalledCount);
    }

    /**
     * Tests orphan removal during unlink attempts to remove the expected
     * directories for unlinked files.
     *
     * This method is very 'hacky' and relies alot on the execution order
     * within the delete orphans method of the link file handler. It does
     * however managed to achieve a very concise coverage of the method and
     * tests of the various scenarios.
     *
     * @dataProvider dataProviderOrphanDirectoryDelete
     *
     * @param string $rootPath
     *     The root path to set against the link file handler
     *     Force set makes data provider parameter sets more clear.
     * @param string $destinationDir
     *     The destination dir to set against the link definition
     *     Again forcing this to be provided makes for clearer paramter sets
     *     within the date provider.
     * @param string[] $fileMappings
     *     A flat array of file mappings to set against the link definition
     *     Leave blank to test orphan removal when handling full directory
     *     unlink.
     * @param string[] $nonEmptyDirs
     *     An array of directories to treat as non empty during this test run
     *     These should be the absolute paths of the directory containing the
     *     $rootPath and $destinationDir where required
     * @param string[] $expectedDeleteDirs
     *     An array of expected orphan directories that are deleted during
     *     orphan directory removal.
     *     IMPORTANT: Include only orphan directories here, the path to a full
     *     linked directory is not an orphan and will be removed regardless, it
     *     is not an orphan. Neither are paths to distinct file mappings,
     *
     * @return void
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function testOrphanDirectoryDeleteDuringUnlink(
        string $rootPath,
        string $destinationDir,
        array $fileMappings,
        array $nonEmptyDirs,
        array $expectedDeleteDirs
    ): void {
        // Set the root path against the file handler
        $this->setPropertyValue(
            $this->linkFileHandler,
            'rootPath',
            $rootPath
        );

        // Create the link definition with a known destination dir
        // Configure it to delete orphan directories
        $linkDefinition = $this->createLinkDefinition($destinationDir);
        $linkDefinition->setDeleteOrphanDirs(true);

        // Link destination root absolute
        // Get the absolute path to the definition destination dir
        // Required in the generation of delete parameters to ignore
        $linkDefinitionDestinationRootAbsolute = $this->callObjectMethod(
            $this->linkFileHandler,
            'getAbsolutePath',
            $linkDefinition->getDestinationDir(),
            $rootPath
        );

        // Determine which parameters passed to the file system remove method
        // should be ignored. This is required as the values passed to that
        // method are compared against the $expectedDeleteDirs parameters passed
        // to this method
        $ignoreDeletes = [];
        if (empty($fileMappings)) {
            // Full directory links, only ignore the absolute path to the
            // full directory, it is not an orphan as it is expected to be
            // removed so ignore it
            $ignoreDeletes[] = $linkDefinitionDestinationRootAbsolute;
        }
        else {
            // For link definition with specific mappings
            foreach ($fileMappings as $i => $dest) {
                // Add the mapping to the definition
                $linkDefinition->addFileMapping('source-'.$i.'.txt', $dest);

                // Ensure the deletion of the actual mapped files are ignored
                // so they are not compared to the $expectedDeleteDirs method
                // parameter. Again, this method should only test against
                // orphan deletions, not expected deletions due to unlinking.
                $ignoreDeletes[] = $this->callObjectMethod(
                    $this->linkFileHandler,
                    'getAbsolutePath',
                    $dest,
                    $linkDefinitionDestinationRootAbsolute
                );
            }
        }

        // Black magix time, create an anonymous class that can be treat as
        // a file system mock that will be injected into runtime via uopz.
        // The class is simple, it desfines an accessible $valid property that
        // can be altered prior to calling the valid method. Valid method is
        // used by the link file handler to ensure directories are empty. True
        // would mean directory not empty, false, directory is empty
        $fileSystemIteratorMock = new Class {
            /** @var bool */
            public static $valid;

            public function valid(): bool
            {
                return self::$valid;
            }
        };

        // Initialise an array of tracked delete dirs
        // The values of this array are populated during the filesystem remove
        // method mock as required. It is also used in runtime mocks.
        $actualDeletedDirs = [];

        // Override the internal is_dir function.
        // Black magic again, we know this function is used in the remove orphan
        // methods so we can determine it's values based on the tracking arrays
        // created. We also have to hijack execution order here to ensure the
        // file system mock returns an expected value. is_dir is called before
        // file system iterator valid, so it can be 'safely' used to dictate the
        // return value of that method.
        // phpcs:ignore
        uopz_set_return('is_dir', static function ($arg) use (&$actualDeletedDirs, $nonEmptyDirs, &$fileSystemIteratorMock) {
            // If the file system remove method has already removed this directory
            // in psuedo world it does not exist
            if (in_array($arg, $actualDeletedDirs, true)) {
                return false;
            }

            // Prepare the file system iterator mock valid return value based
            // on whether the directory being checked has been defined as non
            // empty in this test case method parameters.
            $fileSystemIteratorMock::$valid = in_array($arg, $nonEmptyDirs, true);

            return true;
        }, true);

        // Inject the file system mock into runtime
        uopz_set_mock(FilesystemIterator::class, $fileSystemIteratorMock);

        // Configure the filesystem service to track the deleted dirs
        $this->symfonyFileSystem
            ->method('remove')
            ->willReturnCallback(static function ($removeMethodParam) use (&$actualDeletedDirs, $ignoreDeletes) {
                // only track the deleted dirs that are not to be ignored
                // ie. full link dir deletion, file mapping file deletion
                if (!in_array($removeMethodParam, $ignoreDeletes, true)) {
                    $actualDeletedDirs[] = $removeMethodParam;
                }
            });

        // Run the unlink, populating the actual deleted dirs array allowing it
        // to be asserted against.
        $this->linkFileHandler->unlink($linkDefinition);

        // Check that only the expected folders were deleted as defined
        $this->assertArraySame($expectedDeleteDirs, $actualDeletedDirs);

        // Revert run time overrides
        /** @noinspection PhpParamsInspection */ // because of stub errors in phpstorm
        uopz_unset_return('is_dir');
        uopz_unset_mock(FilesystemIterator::class);
    }

    /**
     * Tests that during deletion of orphan directories after package unlinking
     * if an exception is thrown by the internal filesystem is caught by the
     * file handler, logged and stops execution
     *
     * @dataProvider dataProviderOrphanDirectoryDeleteException
     *
     * @param string $rootPath
     * @param string $destDir
     * @param array $fileMappings
     * @param array $throwExceptionAtPaths
     * @param array $expectedDeletePaths
     *
     * @return void
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function testOrphanDirectoryDeletionExceptionAreCaughtAndLogged(
        string $rootPath,
        string $destDir,
        array $fileMappings,
        array $throwExceptionAtPaths,
        array $expectedDeletePaths
    ): void {
        // Set a know root path for clarity
        $this->setPropertyValue(
            $this->linkFileHandler,
            'rootPath',
            $rootPath,
        );

        // Create a counter to store loggers calls
        $loggerCalledCount = 0;

        // Create the mocked logger using the above callback to track calls
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->method('warning')
            ->with(static function () use (&$loggerCalledCount) {
                $loggerCalledCount++;
            });
        $logger
            ->method('log')
            ->willReturnCallback(static function ($loglevel) use (&$loggerCalledCount) {
                if ($loglevel === LogLevel::WARNING) {
                    $loggerCalledCount++;
                }
            });

        // Set the logger against the file handler
        $this->linkFileHandler->setLogger($logger);

        // Ensure is dir internal always returns true when searching orphan dirs
        /** @noinspection PhpParamsInspection */ // because of stub errors in phpstorm
        uopz_set_return('is_dir', true);

        // Ensure file system iterator returns false for valid (ie, dir empty)
        uopz_set_mock(FilesystemIterator::class, new class {
            public function valid(): bool
            {
                return false;
            }
        });

        // Create a link definition with a nested destination dir
        // During standard process, the expected orphan directories to be
        // deleted by the link file handler, if empty are:
        // /root/path/nested/destination
        // /root/path/nested
        $linkDefinition = $this->createLinkDefinition($destDir);
        $linkDefinition->setDeleteOrphanDirs(true);

        // Get the determined absolute destination dir path, use it in
        // resolving of remove paths to ignore
        $linkDefinitionDestinationRootAbsolute = $this->callObjectMethod(
            $this->linkFileHandler,
            'getAbsolutePath',
            $linkDefinition->getDestinationDir(),
            $rootPath
        );

        // Determine remove ignore paths
        $removeIgnorePaths = [];
        if (empty($fileMappings)) {
            // File mappings empty, simple directory link definition
            // add only the actual dir link to ignores
            $removeIgnorePaths[] = $linkDefinitionDestinationRootAbsolute;
        }
        else {
            // Add the absolute path to each linked file
            foreach ($fileMappings as $i => $dest) {
                // Add the mapping to the link definition
                $linkDefinition->addFileMapping('source-'.$i.'txt', $dest);

                // Add the ignore record
                $removeIgnorePaths[] = $this->callObjectMethod(
                    $this->linkFileHandler,
                    'getAbsolutePath',
                    $dest,
                    $linkDefinitionDestinationRootAbsolute
                );
            }
        }

        // Create storage array for orphan delete calls and exception calls
        $actualDeletePaths = [];
        $actualExceptionPaths = [];

        // Mock the file handler instance
        $this->symfonyFileSystem
            ->method('remove')
            //phpcs:ignore
            ->willReturnCallback(static function ($removeMethodParam) use ($throwExceptionAtPaths, $removeIgnorePaths, &$actualDeletePaths, &$actualExceptionPaths) {
                // ignore the non orphan deletes
                if (in_array($removeMethodParam, $removeIgnorePaths, true)) {
                    return true;
                }

                // throw exception as configured
                if (in_array($removeMethodParam, $throwExceptionAtPaths, true)) {
                    $actualExceptionPaths[] = $removeMethodParam;

                    throw new SymfonyFileSystemInvalidArgumentException('orphan exception');
                }

                // Orphan path found, not throwing exception, store it for
                // checking if not already stored
                if (!in_array($removeMethodParam, $actualDeletePaths, true)) {
                    $actualDeletePaths[] = $removeMethodParam;
                }

                return true;
            });

        // Run the unlink capturing number of times logged
        try {
            $this->linkFileHandler->unlink($linkDefinition);
        }
        catch (Exception $e) {
            $this->assertFalse(true, 'Unexpected exception thrown during unlink');
        }

        // Ensure all the paths that were expected to be deleted prior to
        // exception calls were deleted
        $this->assertArraySame($expectedDeletePaths, $actualDeletePaths);

        // Ensure the logger logged a warning for every exception path
        $this->assertSame(count($actualExceptionPaths), $loggerCalledCount);

        // Ensure the exceptions were thrown for every expected exception path
        // Use array_unique to remove duplicate exception paths for file branches
        // with same root exception dir. And use array values to ensure no index
        // key mismatches caused by removal of duplicates.
        $this->assertArraySame(
            array_values($throwExceptionAtPaths),
            array_values(array_unique($actualExceptionPaths))
        );

        // Revert run time overrides
        /** @noinspection PhpParamsInspection */ // because of stub errors in phpstorm
        uopz_unset_return('is_dir');
        uopz_unset_mock(FilesystemIterator::class);
    }

    /**
     * Data provider for the exceptions logging during orphan deletion test.
     *
     * @see testOrphanDirectoryDeletionExceptionAreCaughtAndLogged
     *
     * @return array|string[]
     */
    public function dataProviderOrphanDirectoryDeleteException(): array
    {
        return [
            'dir mapping' => [
                '/root/path',
                'very/nested/destination/dir',
                [],
                [
                    '/root/path/very'
                ],
                [
                    '/root/path/very/nested/destination',
                    '/root/path/very/nested'
                ]
            ],
            'file mapping' => [
                '/root/path',
                'very/nested/destination/dir',
                [
                    'simple.txt',
                    'branch-1/again.txt',
                    'branch-2/deep/nested/file.txt'
                ],
                [
                    '/root/path/very',
                    '/root/path/very/nested/destination/dir/branch-2/deep'
                ],
                [
                    '/root/path/very/nested/destination/dir/branch-2/deep/nested',
                    '/root/path/very/nested/destination/dir/branch-1',
                    '/root/path/very/nested/destination/dir',
                    '/root/path/very/nested/destination',
                    '/root/path/very/nested'
                ]
            ]
        ];
    }

    /**
     * Provides test case data for the testItLinksADirectory test case.
     *
     * @return array|array[]
     */
    public function dataProviderLinkDirectory(): array
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        return [
            // Input destination, uses copy?, expected destination dir, set root path (optional)
            'absolute symlink' => ['/destination/dir', false, '/destination/dir'],
            'absolute copy' => ['/destination/dir', true, '/destination/dir'],
            'relative symlink' => ['relative/dir', false, realpath(getcwd()).'/relative/dir'],
            'relative copy' => ['relative/dir', true, realpath(getcwd()).'/relative/dir'],
            'set root path absolute symlink' => ['/destination/dir', false, '/destination/dir', __DIR__],
            'set root path absolute copy' => ['/destination/dir', true, '/destination/dir', __DIR__],
            'set root path relative symlink' => ['relative/dir', false, __DIR__.'/relative/dir', __DIR__],
            'set root path relative copy' => ['relative/dir', true, __DIR__.'/relative/dir', __DIR__],
            'relative copy dot' => ['./relative/dir', true, '/root/dir/relative/dir', '/root/dir'],
            'relative copy parent' => ['../relative/dir', true, '/root/relative/dir', '/root/dir'],
            'relative copy dot parents' => ['./../../relative/dir', true, '/root/relative/dir', '/root/dir/another'],
            'relative symlink dot' => ['./relative/dir', false, '/root/dir/relative/dir', '/root/dir'],
            'relative symlink parent' => ['../relative/dir', false, '/root/relative/dir', '/root/dir'],
            'relative symlink dot parents' => ['./../../relative/dir', false, '/root/relative/dir', '/root/dir/another']
        ];
        // phpcs:enable Generic.Files.LineLength.TooLong
    }

    /**
     * Provides data for the link files test
     *
     * @return array[]
     *     An array of parameter sets containing:
     *         - Link file handler root path
     *         - Link definition destination dir
     *         - Array of file mappings to add to the link definition
     *               Sub arrays where
     *                   index 0 = source file
     *                   index 1 = dest file
     *         - Array of expected values to be called on the filesystem service
     *           inside of the link file handler class
     *               Sub arrays where
     *                   index 0 = expected source input
     *                   index 1 = expected dest write path
     */
    public function dataProviderLinkFiles(): array
    {
        return [
            [
                '/root/path',
                './destination/dir',
                [
                    ['source.txt', '/dest.txt'], // absolute path
                    ['source.txt', 'dest.txt'] // relative path
                ],
                [
                    [self::TEST_PACKAGE_INSTALL_DIR.'/source.txt', '/dest.txt'],
                    [self::TEST_PACKAGE_INSTALL_DIR.'/source.txt', '/root/path/destination/dir/dest.txt']
                ]
            ]
        ];
    }

    /**
     * Data provider for the link exception interception test
     *
     * @see testItInterceptsAndLogsExceptionsDuringLinking()
     *
     * @return array[]
     *     Array of parameter sets where value at:
     *     index 0 => use copy boolean
     *     index 1 => array of file mappings, source => dest
     */
    public function dataProviderLinkExceptionInterception(): array
    {
        return [
            'no copy directory link' => [
                false,
                []
            ],
            'use copy directory link' => [
                true,
                []
            ],
            'no copy file link' => [
                false,
                [
                    'source.txt' => 'dest.txt'
                ]
            ],
            'use copy file link' => [
                true,
                [
                    'source.txt' => 'dest.txt'
                ]
            ]
        ];
    }

    /**
     * Provides test data for directory unlinking
     *
     * @return array
     */
    public function dataProviderUnlinkDirectory(): array
    {
        return [
            // input destination dir, expected delete dir
            'relative path' => ['destination/dir', realpath(getcwd()).'/destination/dir'],
            'absolute path' => ['/absolute/dir', '/absolute/dir']
        ];
    }

    /**
     * Provides data for orphan delete tests
     *
     * @return array[]
     *     An array of parameter sets where each element in the set at:
     *         0 => link file handler root path
     *         1 => link definition destination dir
     *         2 => file mappings, only destination file
     *         3 => expected delete orphan directory paths
     *              IMPORTANT: In directory links, do not include the main
     *              linked directory, this is technically not an orphan and
     *              is deleted regardless of orphan cleanup. Same for file
     *              mappings, dont list the files being delete these are not
     *              orphan directories
     */
    public function dataProviderOrphanDirectoryDelete(): array
    {
        return [
            'absolute directory link, no files, no non empty dirs' => [
                '/root/path',
                '/destination/dir',
                [], // no file mappings
                [], // non non empty dirs
                [] // no orphans deleted
            ],
            'relative directory link, no files, no non empty dirs' => [
                '/root/path',
                'destination/dir',
                [], // No file mappings
                [], // no non empty dirs
                [
                    // dont include the below, its the actual linked directory, not an orphan
                    // '/root/path/destination/dir',
                    '/root/path/destination'
                ]
            ],
            'relative directory link, no files, has non empty dirs' => [
                '/root/path',
                'very/nested/destination/dir/for/linking',
                [], //no file mappings
                [
                    '/root/path/very/nested/destination' // claim has other files in
                ],
                [
                    '/root/path/very/nested/destination/dir/for',
                    '/root/path/very/nested/destination/dir'
                ]
            ],
            'relative directory link, mapped files, no non empty dirs' => [
                '/root/path',
                'destination/dir',
                [
                    'nested/file/in/here.txt'
                ],
                [],
                [
                    '/root/path/destination/dir/nested/file/in',
                    '/root/path/destination/dir/nested/file',
                    '/root/path/destination/dir/nested',
                    '/root/path/destination/dir',
                    '/root/path/destination'
                ]
            ],
            'relative directory link, mapped files, has non empty dirs' => [
                '/root/path',
                'destination/dir',
                [
                    'nested/file/in/here.txt',
                    'another/test/file.txt',
                    'another/file.txt',
                    '/absolute/but/nested/again.txt' // nothing to delete for this as absolute
                ],
                [
                    '/root/path/destination/dir/nested/file'
                ],
                [
                    '/root/path/destination/dir/nested/file/in',
                    // dont delete '/root/path/destination/dir/nested/file' because it has files in it
                    // done delete anything under the above cos found file
                    // move onto the dir structure of the next mapped file below
                    '/root/path/destination/dir/another/test',
                    '/root/path/destination/dir/another',
                    '/root/path/destination/dir',
                    '/root/path/destination'
                ]
            ]
        ];
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

        // Have the installation manager return a known path when dealing with
        // this package
        $this->installationManager
            ->method('getInstallPath')
            ->with($package)
            ->willReturn(self::TEST_PACKAGE_INSTALL_DIR);

        return new LinkDefinition($package, $destinationDir);
    }
}
