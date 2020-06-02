<?php
/**
 * @file
 * LinkCommandTest.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Commands;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\LinkCommand;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageLocatorInterface;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor;
use JParkinson1991\ComposerLinkerPlugin\Tests\Support\ReflectionMutatorTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class LinkCommandTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Commands
 */
class LinkCommandTest extends TestCase
{
    /**
     * Use reflection accessors
     */
    use ReflectionMutatorTrait;

    /**
     * Testable link command
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Composer\Commands\LinkCommand
     */
    protected $command;

    /**
     * Mocked link executor object that has been injected into $command
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $linkExecutor;

    /**
     * Sets up the test cases prior to running
     *
     * @return void
     */
    public function setUp(): void
    {
        $packageLocator = $this->createMock(PackageLocatorInterface::class);
        $packageLocator
            ->method('getFromRepository')
            ->willReturn($this->createMock(PackageInterface::class));

        // Create an extension of the link command which can have its services
        // mocked easily
        $linkCommand = new class ($packageLocator) extends LinkCommand {
            // Settable mock properties
            public $mockComposer;
            public $mockLinkExecutor;

            // Return the mocked composer
            public function getComposer($required = true, $disablePlugins = null)
            {
                return $this->mockComposer;
            }

            // Return the mocked link executor
            // phpcs:ignore
            protected function createLinkExecutor(Composer $composer, InputInterface $input, OutputInterface $output): LinkExecutor
            {
                return $this->mockLinkExecutor;
            }

            // Override inherited methods, stop breaking
            protected function initialize(InputInterface $input, OutputInterface $output): void
            {
                // do nothing, break nothing
            }
        };

        // Create a mock link executor, add it to the command
        $linkExecutor = $this->createMock(LinkExecutor::class);
        $linkCommand->mockLinkExecutor = $linkExecutor;

        // Create the mock composer instance, working upwards
        // repository -> repository manager -> composer
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->method('getLocalRepository')
            ->willReturn($this->createMock(RepositoryInterface::class));

        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        // Add mock composer to command
        $linkCommand->mockComposer = $composer;

        // Store to properties for test case access
        $this->command = $linkCommand;
        $this->linkExecutor = $linkExecutor;
    }

    /**
     * Test the command uses the expected name stub
     *
     * @return void
     *
     * @throws \ReflectionException
     */
    public function testUsesExpectedNameStub(): void
    {
        $this->assertSame(
            'link',
            $this->callObjectMethod(
                $this->command,
                'nameStub'
            )
        );
    }

    /**
     * Test it does link a package when execution routed to it from parent
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItDoesLinkPackages(): void
    {
        $this->linkExecutor
            ->expects($this->exactly(4))
            ->method('linkPackage');

        $input = $this->createMock(InputInterface::class);
        $input
            ->method('getArgument')
            ->with('package-names')
            ->willReturn([
                'package/one',
                'package/two',
                'package/three',
                'package/four'
            ]);

        $this->command->run(
            $input,
            $this->createMock(OutputInterface::class)
        );
    }

    /**
     * Test it does link a repository when execution routed to it from parent
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItDoesLinkARepository(): void
    {
        $this->linkExecutor
            ->expects($this->once())
            ->method('linkRepository');

        $this->command->run(
            $this->createMock(InputInterface::class),
            $this->createMock(OutputInterface::class)
        );
    }
}
