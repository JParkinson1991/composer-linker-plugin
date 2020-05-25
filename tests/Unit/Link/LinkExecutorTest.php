<?php
/**
 * @file
 * LinkExecutorTesrt.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Link;

use Composer\Package\PackageInterface;
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
     * Tests that in optimal circumstance, the executor links a package
     *
     * @return void
     */
    public function testItExecutesPackageLink(): void
    {
        $package = $this->createMock(PackageInterface::class);

        $linkDefinition = $this->createMock(LinkDefinition::class);

        $linkDefinitionFactory = $this->createMock(LinkDefinitionFactoryInterface::class);
        $linkDefinitionFactory
            ->expects($this->once())
            ->method('createForPackage')
            ->with($package)
            ->willReturn($linkDefinition);

        $linkFileHandler = $this->createMock(LinkFileHandlerInterface::class);
        $linkFileHandler
            ->expects($this->once())
            ->method('link')
            ->with($linkDefinition);

        $linkExecutor = new LinkExecutor($linkDefinitionFactory, $linkFileHandler);
        $linkExecutor->linkPackage($package);
    }

    /**
     * Tests the executor does not attempt to link anything on config not found
     *
     * @return void
     */
    public function testItDoesNotTryLinkAPackageOnConfigNotFound(): void
    {
        $package = $this->createMock(PackageInterface::class);

        $linkDefinitionFactory = $this->createMock(LinkDefinitionFactoryInterface::class);
        $linkDefinitionFactory
            ->method('createForPackage')
            ->with($package)
            ->willThrowException(new ConfigNotFoundException());

        $linkFileHandler = $this->createMock(LinkFileHandlerInterface::class);
        $linkFileHandler
            ->expects($this->never())
            ->method('link');

        try {
            $linkExecutor = new LinkExecutor($linkDefinitionFactory, $linkFileHandler);
            $linkExecutor->linkPackage($package);
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

        $linkDefinitionFactory = $this->createMock(LinkDefinitionFactoryInterface::class);
        $linkDefinitionFactory
            ->method('createForPackage')
            ->with($package)
            ->willThrowException(new InvalidConfigException());

        $linkFileHandler = $this->createMock(LinkFileHandlerInterface::class);
        $linkFileHandler
            ->expects($this->never())
            ->method('link');

        try {
            $linkExecutor = new LinkExecutor($linkDefinitionFactory, $linkFileHandler);
            $linkExecutor->linkPackage($package);
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

        $linkDefinitionFactory = $this->createMock(LinkDefinitionFactoryInterface::class);
        $linkDefinitionFactory
            ->expects($this->once())
            ->method('createForPackage')
            ->with($package)
            ->willReturn($linkDefinition);

        $linkFileHandler = $this->createMock(LinkFileHandlerInterface::class);
        $linkFileHandler
            ->expects($this->once())
            ->method('unlink')
            ->with($linkDefinition);

        $linkExecutor = new LinkExecutor($linkDefinitionFactory, $linkFileHandler);
        $linkExecutor->unlinkPackage($package);
    }

    /**
     * Tests the executor does not attempt to link anything on config not found
     *
     * @return void
     */
    public function testItDoesNotTryUnLinkAPackageOnConfigNotFound(): void
    {
        $package = $this->createMock(PackageInterface::class);

        $linkDefinitionFactory = $this->createMock(LinkDefinitionFactoryInterface::class);
        $linkDefinitionFactory
            ->method('createForPackage')
            ->with($package)
            ->willThrowException(new ConfigNotFoundException());

        $linkFileHandler = $this->createMock(LinkFileHandlerInterface::class);
        $linkFileHandler
            ->expects($this->never())
            ->method('unlink');

        try {
            $linkExecutor = new LinkExecutor($linkDefinitionFactory, $linkFileHandler);
            $linkExecutor->unlinkPackage($package);
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

        $linkDefinitionFactory = $this->createMock(LinkDefinitionFactoryInterface::class);
        $linkDefinitionFactory
            ->method('createForPackage')
            ->with($package)
            ->willThrowException(new InvalidConfigException());

        $linkFileHandler = $this->createMock(LinkFileHandlerInterface::class);
        $linkFileHandler
            ->expects($this->never())
            ->method('unlink');

        try {
            $linkExecutor = new LinkExecutor($linkDefinitionFactory, $linkFileHandler);
            $linkExecutor->linkPackage($package);
        }
        catch (InvalidConfigException $e) {
            $this->addToAssertionCount(1);
        }
    }
}
