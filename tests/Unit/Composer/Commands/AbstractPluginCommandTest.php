<?php
/**
 * @file
 * NewAbstractPluginCommandTest.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Commands;

use Composer\Composer;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Exception;
use InvalidArgumentException;
use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\AbstractPluginCommand;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageLocatorInterface;
use JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorExceptionCollection;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor;
use JParkinson1991\ComposerLinkerPlugin\Tests\Support\ArrayTestTrait;
use JParkinson1991\ComposerLinkerPlugin\Tests\Support\ReflectionMutatorTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class NewAbstractPluginCommandTest
 *
 * This test class covers testing of how AbstractPluginCommand uses its abstract methods in the creation of a concrete
 * command instance. Actual command functionality testing should be tested per command via integration tests in a mocked
 * composer environment.
 *
 * @see \JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Commands\BaseCommandTest
 * @see \JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\BaseComposerTestCase
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Commands
 */
class AbstractPluginCommandTest extends TestCase
{
    /**
     * A mocked extension of AbstractPluginCommand
     *
     * @var AbstractPluginCommand
     *
     * @see setUp()
     */
    private $command;

    /**
     * Sets up the class prior to the execution of each test
     *
     * @return void
     */
    public function setUp(): void
    {
        $packageLocator = $this->createMock(PackageLocatorInterface::class);

        $this->command = new class ($packageLocator) extends AbstractPluginCommand
        {
            protected function nameStub(): string
            {
                return 'test';
            }

            protected function description(): string
            {
                return 'test description';
            }

            protected function doExecutePackage(LinkExecutor $linkExecutor, PackageInterface $package): void
            {
            }

            protected function doExecuteRepository(LinkExecutor $linkExecutor, RepositoryInterface $repository): void
            {
            }
        };
    }

    /**
     * Tests that an extending command class's name stub is properly configured
     *
     * @return void
     */
    public function testItConfiguresName(): void
    {
        $this->assertSame('composer-linker-plugin:test', $this->command->getName());
    }

    /**
     * Tests that an extending command class's description is properly configured
     *
     * @return void
     */
    public function testItConfiguresDescription(): void
    {
        $this->assertSame('test description', $this->command->getDescription());
    }

    /**
     * Tests that an extending command class's name stub is used to properly configure command aliases
     *
     * @return void
     */
    public function testItConfiguresAlias(): void
    {
        $this->assertContains('clp-test', $this->command->getAliases());
    }

    /**
     * Tests that an extending classes arguments are properly configured
     *
     * @return void
     */
    public function testItConfiguresPackageNamesArgument(): void
    {
        $this->assertTrue($this->command->getDefinition()->hasArgument('package-names'));

        $argument = $this->command->getDefinition()->getArgument('package-names');

        $this->assertTrue($argument->isArray());
        $this->assertFalse($argument->isRequired());
    }
}
