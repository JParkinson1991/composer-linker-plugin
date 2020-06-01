<?php
/**
 * @file
 * AbstractPluginCommandTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Commands;

use Composer\Composer;
use Composer\Installer\InstallationManager;
use Composer\Package\RootPackageInterface;
use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\AbstractPluginCommand;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor;
use JParkinson1991\ComposerLinkerPlugin\Tests\Support\ArrayTestTrait;
use JParkinson1991\ComposerLinkerPlugin\Tests\Support\ReflectionMutatorTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractPluginCommandTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Commands
 */
abstract class AbstractPluginCommandTest extends TestCase
{
    /**
     * Leverage array assertations
     */
    use ArrayTestTrait;

    /**
     * Leverage reflection access to protected/private properties/methods
     */
    use ReflectionMutatorTrait;

    /**
     * Returns the instance to execute abstracted tests against
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Composer\Commands\AbstractPluginCommand
     */
    abstract protected function getInstance(): AbstractPluginCommand;

    /**
     *
     * @return void
     */
    public function testItConfiguresChildCommands(): void
    {
        $instance = $this->getInstance();
        $instanceNameStub = $this->callObjectMethod($instance, 'nameStub');
        $instanceDescription = $this->callObjectMethod($instance, 'description');

        // Assert name stub added to standard command prefix
        $this->assertSame(
            'composer-linker-plugin:'.$instanceNameStub,
            $instance->getName()
        );

        // Check name stub added to alias prefix
        $this->assertArraySame(
            ['clp-'.$instanceNameStub],
            $instance->getAliases()
        );

        // Check description configured as set by child class
        $this->assertSame($instanceDescription, $instance->getDescription());

        // Check argument added as expected
        $packageNameArgument = $instance->getDefinition()->getArgument('package-names');
        $this->assertInstanceOf(InputArgument::class, $packageNameArgument);
        $this->assertTrue($packageNameArgument->isArray());
    }

    /**
     * Tests that the abstract class can instantiate a link executor
     *
     * @return void
     */
    public function testInInstantiatesALinkExecutor(): void
    {
        $instance = $this->getMockBuilder(get_class($this->getInstance()))
            ->onlyMethods(['getHelperSet'])
            ->getMock();

        $instance
            ->method('getHelperSet')
            ->willReturn($this->createMock(HelperSet::class));

        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getPackage')
            ->willReturn($this->createMock(RootPackageInterface::class));
        $composer
            ->method('getInstallationManager')
            ->willReturn($this->createMock(InstallationManager::class));

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $linkExecutor = $this->callObjectMethod(
            $instance,
            'createLinkExecutor',
            $composer,
            $input,
            $output
        );

        $this->assertInstanceOf(LinkExecutor::class, $linkExecutor);
    }
}
