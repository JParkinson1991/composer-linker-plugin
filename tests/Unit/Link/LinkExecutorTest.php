<?php
/**
 * @file
 * LinkExecutorTesrt.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Link;

use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException;
use JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorExceptionCollection;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactoryInterface;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandlerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class LinkExecutorTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Link
 */
class LinkExecutorTest extends TestCase
{
    /**
     * The mock link definition factory service
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $linkDefinitionFactory;

    /**
     * The link executor instance with mocked services available via class
     * properties
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor
     */
    protected $linkExecutor;

    /**
     * The mocked link file handler service
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $linkFileHandler;

    /**
     * Setups a a link executor with instance with accessible service mocks
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->linkDefinitionFactory = $this->createMock(LinkDefinitionFactoryInterface::class);
        $this->linkFileHandler = $this->createMock(LinkFileHandlerInterface::class);
        $this->linkExecutor = new LinkExecutor(
            $this->linkDefinitionFactory,
            $this->linkFileHandler
        );
    }

    /**
     * Tests that in optimal circumstance, the executor links a package
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItExecutesPackageLink(): void
    {
        $package = $this->createMock(PackageInterface::class);

        $linkDefinition = $this->createMock(LinkDefinition::class);

        $this->linkDefinitionFactory
            ->expects($this->once())
            ->method('createForPackage')
            ->with($package)
            ->willReturn($linkDefinition);

        $this->linkFileHandler
            ->expects($this->once())
            ->method('link')
            ->with($linkDefinition);

        $this->linkExecutor->linkPackage($package);
    }

    /**
     * Tests the executor does not attempt to link anything on config not found
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItDoesNotTryLinkAPackageOnConfigNotFound(): void
    {
        $package = $this->createMock(PackageInterface::class);

        $this->linkDefinitionFactory
            ->method('createForPackage')
            ->with($package)
            ->willThrowException(new ConfigNotFoundException());

        $this->linkFileHandler
            ->expects($this->never())
            ->method('link');

        try {
            $this->linkExecutor->linkPackage($package);
        }
        catch (LinkExecutorException $e) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * Tests the executor does not attempt to link anything on invalid config
     * exception
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItDoesNotTryLinkAPackageOnInvalidConfig(): void
    {
        $package = $this->createMock(PackageInterface::class);

        $this->linkDefinitionFactory
            ->method('createForPackage')
            ->with($package)
            ->willThrowException(new InvalidConfigException());

        $this->linkFileHandler
            ->expects($this->never())
            ->method('link');

        try {
            $this->linkExecutor->linkPackage($package);
        }
        catch (LinkExecutorException $e) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * Tests under optimal circumstance the executor will unlink a package
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItUnlinksAPackage(): void
    {
        $package = $this->createMock(PackageInterface::class);

        $linkDefinition = $this->createMock(LinkDefinition::class);

        $this->linkDefinitionFactory
            ->expects($this->once())
            ->method('createForPackage')
            ->with($package)
            ->willReturn($linkDefinition);

        $this->linkFileHandler
            ->expects($this->once())
            ->method('unlink')
            ->with($linkDefinition);

        $this->linkExecutor->unlinkPackage($package);
    }

    /**
     * Tests the executor does not attempt to link anything on config not found
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItDoesNotTryUnLinkAPackageOnConfigNotFound(): void
    {
        $package = $this->createMock(PackageInterface::class);

        $this->linkDefinitionFactory
            ->method('createForPackage')
            ->with($package)
            ->willThrowException(new ConfigNotFoundException());

        $this->linkFileHandler
            ->expects($this->never())
            ->method('unlink');

        try {
            $this->linkExecutor->unlinkPackage($package);
        }
        catch (LinkExecutorException $e) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * Tests the executor does not attempt to link anything on invalid config
     * exception
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItDoesNotTryUnLinkAPackageOnInvalidConfig(): void
    {
        $package = $this->createMock(PackageInterface::class);

        $this->linkDefinitionFactory
            ->method('createForPackage')
            ->with($package)
            ->willThrowException(new InvalidConfigException());

        $this->linkFileHandler
            ->expects($this->never())
            ->method('unlink');

        try {
            $this->linkExecutor->linkPackage($package);
        }
        catch (LinkExecutorException $e) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * Tests that the executor is able to unlink all necessary packages within
     * a given repository
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItUnlinksARepository(): void
    {
        $package1 = $this->createMock(PackageInterface::class);
        $package2 = $this->createMock(PackageInterface::class);

        $repository = $this->createMock(RepositoryInterface::class);
        $repository
            ->method('getPackages')
            ->willReturn([$package1, $package2]);

        $this->linkDefinitionFactory
            ->expects($this->exactly(2))
            ->method('createForPackage')
            ->withConsecutive([$package1], [$package2]);

        $this->linkFileHandler
            ->expects($this->exactly(2))
            ->method('unlink');

        $this->linkExecutor->unlinkRepository($repository);
    }

    /**
     * Tests that when the executor unlinks a repository it does not treat
     * config not found exceptions as errors.
     *
     * Unlink an entire repository should essentially act as a finder, finding
     * all relevant packages and unlink them as required.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testUnlinkRepositoryIgnoresConfigNotFoundExceptions(): void
    {
        $package1 = $this->createMock(PackageInterface::class);
        $package2 = $this->createMock(PackageInterface::class);

        $linkDefinition = $this->createMock(LinkDefinition::class);

        $repository = $this->createMock(RepositoryInterface::class);
        $repository
            ->method('getPackages')
            ->willReturn([$package1, $package2]);

        // Have the link definition factory throw an exception for $package1
        // It should still be called twices as config not found is not an
        // error
        $this->linkDefinitionFactory
            ->expects($this->exactly(2))
            ->method('createForPackage')
            ->willReturnCallback(static function ($package) use ($package1, $linkDefinition) {
                if ($package === $package1) {
                    throw new ConfigNotFoundException('no config found');
                }

                return $linkDefinition;
            });

        // Despite the config not found exception, ensure $package2 still
        // unlinked
        $this->linkFileHandler
            ->expects($this->once())
            ->method('unlink');

        try {
            $this->linkExecutor->unlinkRepository($repository);
        }
        catch (LinkExecutorExceptionCollection $e) {
            // Add a test failure if an exception thrown
            // Config not found exceptions should not bubble out of the
            // executor
            $this->assertFalse(
                true,
                'Config not found exception bubbled outside of the executor'
            );
        }
    }

    /**
     * Tests that when unlinking all relevant packages in a repository if
     * exceptions are thrown (other than the ignored config not found) they
     * are caught and added to a collection that is then thrown after
     * processing all packages in that repository.
     *
     * Essentially, dont let one bad apple spoil the bunch.
     *
     * @return void
     */
    public function testUnlinkingRepositoryCatchesProcessExceptionsAndThrowsViaCollection(): void
    {
        // Create 3 test packages, 2 of them will throw exceptions
        $packageOne = $this->createMock(PackageInterface::class);
        $packageTwo = $this->createMock(PackageInterface::class);
        $packageThree = $this->createMock(PackageInterface::class);

        // Create appropriate link definitions
        // $packageTwo wont have one as it throws an errors
        $linkDefinitionOne = $this->createMock(LinkDefinition::class);
        $linkDefinitionThree = $this->createMock(LinkDefinition::class);

        // Mock the link definition factory to throw an exception when
        // encountering $packageTwo, we expect this method to be called for
        // all three packages despite the error
        $this->linkDefinitionFactory
            ->expects($this->exactly(3))
            ->method('createForPackage')
            // phpcs:ignore
            ->willReturnCallback(function ($package) use ($packageOne, $packageTwo, $packageThree, $linkDefinitionOne, $linkDefinitionThree) {
                if ($package === $packageOne) {
                    return $linkDefinitionOne;
                }

                if ($package === $packageTwo) {
                    throw new ConfigNotFoundException('config not found');
                }

                if ($package === $packageThree) {
                    return $linkDefinitionThree;
                }

                return $this->createMock(LinkDefinition::class);
            });

        // Mock the link file handler to throw an exception encountering
        // $linkDefinitionThree, we still expect it to be called twice
        // once for $packageOne, another for $packageThree
        $this->linkFileHandler
            ->expects($this->exactly(2))
            ->method('unlink')
            ->willReturnCallback(static function ($linkDefinition) use ($linkDefinitionThree) {
                if ($linkDefinition === $linkDefinitionThree) {
                    throw new InvalidConfigException('invalid config');
                }
            });

        // Mock a repository returning the mock packages
        $repository = $this->createMock(RepositoryInterface::class);
        $repository
            ->method('getPackages')
            ->willReturn([$packageOne, $packageTwo, $packageThree]);

        try {
            $this->linkExecutor->unlinkRepository($repository);
            $this->assertFalse(true, 'No exception thrown');
        }
        catch (LinkExecutorExceptionCollection $e) {
            $this->addToAssertionCount(1);

            // The exception collection should contain one exception as
            // config not found exceptions are ignored when linking repositories
            $this->assertCount(1, $e->getExceptions());
        }
    }
}
