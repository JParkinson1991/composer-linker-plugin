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
        catch (ConfigNotFoundException $e) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * Tests the executor does not attempt to link anything on invalid config
     * exception
     *
     * @return void
     */
    public function testItDoesNotTryLinkAPackageOnInvalidConfig()
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
        catch (InvalidConfigException $e) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * Tests under optimal circumstance the executor will unlink a package
     *
     * @return void
     */
    public function testItUnlinksAPackage()
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
        catch (ConfigNotFoundException $e) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * Tests the executor does not attempt to link anything on invalid config
     * exception
     *
     * @return void
     */
    public function testItDoesNotTryUnLinkAPackageOnInvalidConfig()
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
        catch (InvalidConfigException $e) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * Tests that the executor is able to unlink all necessary packages within
     * a given repository
     *
     * @return void
     */
    public function testItUnlinksARepository()
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
     */
    public function testUnlinkRepositoryIgnoresConfigNotFoundExceptions()
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
            ->willReturnCallback(function ($package) use ($package1, $linkDefinition) {
                if ($package === $package1) {
                    throw new ConfigNotFoundException();
                }

                return $linkDefinition;
            });

        // Despite the config not found exception, ensure $package2 still
        // unlinked
        $this->linkFileHandler
            ->expects($this->exactly(1))
            ->method('unlink');

        try {
            $this->linkExecutor->unlinkRepository($repository);
        }
        catch (ConfigNotFoundException $e) {
            // Add a test failure if config not found exception was not
            // caught by the executor
            $this->assertFalse(
                true,
                'Config not found exception thrown when unlinking a repository'
            );
        }
    }
}
