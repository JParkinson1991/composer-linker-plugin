<?php
/**
 * @file
 * NewAbstractPluginCommandTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Commands;

use Composer\Composer;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
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
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class NewAbstractPluginCommandTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Commands
 */
class AbstractPluginCommandTest extends TestCase
{
    /**
     * Uses the array assertations trait
     */
    use ArrayTestTrait;

    /**
     * Reflection access methods
     */
    use ReflectionMutatorTrait;

    /**
     * A testable extension of the of the abstract command
     *
     * @var AbstractPluginCommand
     */
    protected $instance;

    /**
     * Sets up a testable instance property
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->instance = $this->newTestableInstance();
    }

    /**
     * Tests the abstract configures extending commands as expected
     *
     * @return void
     */
    public function testItConfiguresChildCommands(): void
    {
        // 'test' taken from instantiation method defaults
        $this->assertSame(
            'composer-linker-plugin:test',
            $this->instance->getName()
        );

        // 'test' taken from instantiation method defaults
        $this->assertArraySame(
            ['clp-test'],
            $this->instance->getAliases()
        );

        // 'test command' taken from instantiation method defaults
        $this->assertSame(
            'test command',
            $this->instance->getDescription()
        );

        // Check argument added as expected
        $this->assertTrue($this->instance->getDefinition()->hasArgument('package-names'));
        $packageNameArgument = $this->instance->getDefinition()->getArgument('package-names');
        $this->assertInstanceOf(InputArgument::class, $packageNameArgument);
        $this->assertTrue($packageNameArgument->isArray());
    }

    /**
     * Tests that extending command is not executed to completion nor does any
     * link execution occur if the composer instance can not be loaded
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testCommandExecutionStopsIfComposerNotLoaded(): void
    {
        // Set the link executor to be returned from createLinkExecutor
        // This allows observation of the methods called on it
        $linkExecutor = $this->createMock(LinkExecutor::class);
        $linkExecutor
            ->expects($this->never())
            ->method($this->anything());

        // Set a null value for composer, meaning when the abstract class
        // attempts to fetch it null is returned, which should stop execution
        // Set the observed link executor into the testable instance also
        $this->setTestableInstanceComposerMock(null);
        $this->setTestableInstanceLinkExecutorMock($linkExecutor);

        // Run the command get it's exit code
        $exitCode = $this->instance->run(
            $this->createMock(InputInterface::class),
            $this->createMock(OutputInterface::class)
        );

        // Ensure exited with error
        $this->assertSame(1, $exitCode);
    }

    /**
     * Test command execution is stopped if invalid arguments are passed to
     * the abstract command
     *
     * @return void
     * @throws \Exception
     */
    public function testCommandExecutionStopsOnInvalidArguments(): void
    {
        // Create a package locator mock
        $packageLocator = $this->createMock(PackageLocatorInterface::class);
        $packageLocator
            ->method('getFromRepository')
            ->willThrowException(new InvalidArgumentException());

        // Create a new testable instance using the package locator mock
        // Set it against the test class, so other internal helpers target it
        $testableInstance = $this->newTestableInstance($packageLocator);
        $this->instance = $testableInstance;

        // Create a testable composer returning a dummy repository
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->method('getLocalRepository')
            ->willReturn($this->createMock(RepositoryInterface::class));

        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        $this->setTestableInstanceComposerMock($composer);

        // Prepare link executor mock, ensure it's never called
        $linkExecutor = $this->createMock(LinkExecutor::class);
        $linkExecutor
            ->expects($this->never())
            ->method($this->anything());

        // Set it against the testable instance
        $this->setTestableInstanceLinkExecutorMock($linkExecutor);

        // Create an input with some arguments
        $input = $this->createMock(InputInterface::class);
        $input
            ->method('getArgument')
            ->with('package-names')
            ->willReturn(['invalid', 'arguments']);

        // Run the command get it's exit code
        $exitCode = $this->instance->run(
            $input,
            $this->createMock(OutputInterface::class)
        );

        // Ensure exited with error
        $this->assertSame(1, $exitCode);
    }

    /**
     * Tests that the abstract command will route repository execution to the
     * child command when it is run without any arguments
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItRoutesRepositoryExecutionToChildCommandWhenRunWithoutArguments(): void
    {
        // Create a simple mocked repository
        $repository = $this->createMock(RepositoryInterface::class);
        $repository
            ->method('getPackages')
            ->willReturn([]);

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->method('getLocalRepository')
            ->willReturn($repository);

        // Create the mocked composer instance eventually returning composer
        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        $this->setTestableInstanceComposerMock($composer);

        // Run the command with no input arguments configured
        $exitCode = $this->instance->run(
            $this->createMock(InputInterface::class),
            $this->createMock(OutputInterface::class)
        );

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, $this->getCountDoExecuteRepository());
    }

    /**
     * Tests that the abstract command catches and handles any exceptions that
     * are thrown by the child class when running the execution
     *
     * @return void
     */
    public function testItHandlesRepositoryExecutionExceptions(): void
    {
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->method('getLocalRepository')
            ->willReturn($this->createMock(RepositoryInterface::class));

        // Create the mocked composer instance eventually returning composer
        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        $this->setTestableInstanceComposerMock($composer);

        // Create a mock link executor exception with a few nested exceptions
        $linkExecutorExceptionCollection = new LinkExecutorExceptionCollection();
        $linkExecutorExceptionCollection->addException(new LinkExecutorException(
            $this->createMock(PackageInterface::class),
            new InvalidConfigException()
        ));
        $linkExecutorExceptionCollection->addException(new LinkExecutorException(
            $this->createMock(PackageInterface::class),
            new InvalidConfigException()
        ));

        // Create a link executor mock that will throw exception
        $linkExecutor = $this->createMock(LinkExecutor::class);
        $linkExecutor
            ->method('linkRepository') // use link as that is how anonymous class configured
            ->willThrowException($linkExecutorExceptionCollection);

        $this->setTestableInstanceLinkExecutorMock($linkExecutor);

        // Run the command with no input arguments configured
        // Expect at a minimum an exit code of 1 and no exceptions being thrown
        try {
            $exitCode = $this->instance->run(
                $this->createMock(InputInterface::class),
                $this->createMock(OutputInterface::class)
            );
            $this->assertSame(1, $exitCode);
        }
        catch (Throwable $e) {
            $this->assertFalse(true, 'Exception not during handling of repository');
        }
    }

    /**
     * Tests that the command will route package execution to the child command
     * when it is run with package name arguments
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItRoutesPackageExecutionToChildCommandWhenRunWithArguments(): void
    {
        $packageLocator = $this->createMock(PackageLocatorInterface::class);
        $packageLocator
            ->method('getFromRepository')
            ->willReturn($this->createMock(PackageInterface::class));

        // Reinstantiate the test instance with the mock package locator
        // Set to class property for further mutation
        $this->instance = $this->newTestableInstance($packageLocator);

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->method('getLocalRepository')
            ->willReturn($this->createMock(RepositoryInterface::class));

        // Create the mocked composer instance eventually returning composer
        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        $this->setTestableInstanceComposerMock($composer);

        // Create an input with one package name argument
        $input = $this->createMock(InputInterface::class);
        $input
            ->method('getArgument')
            ->with('package-names')
            ->willReturn(['package/one']);

        // Run command
        $exitCode = $this->instance->run(
            $input,
            $this->createMock(OutputInterface::class)
        );

        // Assert ran successful
        $this->assertSame(0, $exitCode);

        // Assert routed to link package for one package from argument
        $this->assertSame(1, $this->getCountDoExecutePackage());

        // Create new input with two more packages
        $input = $this->createMock(InputInterface::class);
        $input
            ->method('getArgument')
            ->with('package-names')
            ->willReturn(['package/two', 'package/three']);

        // Rerun command
        $exitCode = $this->instance->run(
            $input,
            $this->createMock(OutputInterface::class)
        );

        // Assert ran successful
        $this->assertSame(0, $exitCode);

        // Assert routed to link package for two new commands
        // Total will be 2 now, and the 1 previous, so 3
        $this->assertSame(3, $this->getCountDoExecutePackage());
    }

    /**
     * Tests that the abstract command catches and handles any exceptions that
     * are thrown by the child class when running the execution of one or more
     * packages
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testItHandlesPackageExecutionExceptions(): void
    {
        $packageLocator = $this->createMock(PackageLocatorInterface::class);
        $packageLocator
            ->method('getFromRepository')
            ->willReturn($this->createMock(PackageInterface::class));

        // Reinstantiate the test instance with the mock package locator
        // Set to class property for further mutation
        $this->instance = $this->newTestableInstance($packageLocator);

        // Prepare the composer instance
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->method('getLocalRepository')
            ->willReturn($this->createMock(RepositoryInterface::class));

        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        // Set it against the testable instance
        $this->setTestableInstanceComposerMock($composer);

        // Set up a mock link executor to always throw exceptions
        $linkExecutor = $this->createMock(LinkExecutor::class);
        $linkExecutor
            ->method($this->anything())
            ->willThrowException(new LinkExecutorException(
                $this->createMock(PackageInterface::class),
                new Exception()
            ));

        // Set mocked link executor against testable instance
        $this->setTestableInstanceLinkExecutorMock($linkExecutor);

        // Prepare an input with 3 arguments
        $input = $this->createMock(InputInterface::class);
        $input
            ->method('getArgument')
            ->with('package-names')
            ->willReturn(['package/one', 'package/two', 'package/three']);

        // run command
        $exitCode = $this->instance->run(
            $input,
            $this->createMock(OutputInterface::class)
        );

        // Expect error code
        $this->assertSame(1, $exitCode);

        // Expect all three package arguments were attempted to be processed
        $this->assertSame(3, $this->getCountDoExecutePackage());
    }

    /**
     * Creates a testable instance of the abstract command via the use of
     * anonymous classes setting it to the test class for use in test cases
     *
     * Anonymous class is used to enable observation of parent delegation
     * and mockable services which are usually generated dynamically on
     * child command execution
     *
     * @param PackageLocatorInterface $packageLocator
     *      Usually a configured mock locator to instantiate the test abstract
     *      instance with
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Composer\Commands\AbstractPluginCommand
     */
    // phpcs:ignore
    protected function newTestableInstance(PackageLocatorInterface $packageLocator = null): AbstractPluginCommand
    {
        if ($packageLocator === null) {
            $packageLocator = $this->createMock(PackageLocatorInterface::class);
        }

        $instance = new class ($packageLocator) extends AbstractPluginCommand
        {
            // Outside world accessible properties to inject test values into
            public $mockComposer;
            public $mockHelperSet;
            public $mockLinkExecutor;

            // Counters for method call checking
            public $countDoExecutePackage = 0;
            public $countDoExecuteRepository = 0;

            // Return the name stub
            protected function nameStub(): string
            {
                return 'test';
            }

            // Return the description
            protected function description(): string
            {
                return 'test command';
            }

            // Run execution for a package
            protected function doExecutePackage(LinkExecutor $linkExecutor, PackageInterface $package): void
            {
                $this->countDoExecutePackage++;
                $linkExecutor->linkPackage($package);
            }

            // Run execution for a repository
            protected function doExecuteRepository(LinkExecutor $linkExecutor, RepositoryInterface $repository): void
            {
                $this->countDoExecuteRepository++;
                $linkExecutor->linkRepository($repository);
            }

            // Override the link executor method so it a mock can be returned
            // phpcs:ignore
            protected function createLinkExecutor(Composer $composer, InputInterface $input, OutputInterface $output): LinkExecutor
            {
                return $this->mockLinkExecutor ?? parent::createLinkExecutor($composer, $input, $output);
            }

            // Override internal get composer method, capable of returning outside inject mock
            public function getComposer($required = true, $disablePlugins = null)
            {
                return $this->mockComposer;
            }

            // Override internal get helper set method, capable of returning outside inject mock
            public function getHelperSet()
            {
                return $this->mockHelperSet;
            }

            // Override inherited methods, stop breaking
            protected function initialize(InputInterface $input, OutputInterface $output): void
            {
                // do nothing, break nothing
            }
        };

        // Set the mockable values to instance
        $instance->mockComposer = $this->createMock(Composer::class);
        $instance->mockHelperSet = $this->createMock(HelperSet::class);
        $instance->mockLinkExecutor = $this->createMock(LinkExecutor::class);

        return $instance;
    }

    /**
     * Returns the number of times the do execute package method was
     * called by the abstract class
     *
     * @see newTestableInstance()
     * @see newTestableInstance::doExecutePackage()
     *
     * @return int
     */
    protected function getCountDoExecutePackage(): int
    {
        return $this->instance->countDoExecutePackage;
    }

    /**
     * Returns the number of times the do execute repository method was
     * called by the abstract class
     *
     * @see newTestableInstance()
     * @see newTestableInstance::doExecuteRepository()
     *
     * @return int
     */
    protected function getCountDoExecuteRepository(): int
    {
        return $this->instance->countDoExecuteRepository;
    }

    /**
     * Sets a composer mock against the testable abstract instance stored at
     * $this->testableInstance
     *
     * @param \Composer\Composer|null $composer
     *     A mock composer or null to have the class load a null object
     *
     * @see newTestableInstance()
     * @see newTestableInstance::getComposer()
     *
     * @return void
     */
    protected function setTestableInstanceComposerMock(?Composer $composer)
    {
        $this->instance->mockComposer = $composer;
    }

    /**
     * Sets a link executor mock against the testable abstract instance stored
     * at $this->testableInstance.
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor|null $linkExecutor
     *     The mock object or null
     *     Functionality here differs from setting the composer mock
     *     If a null value is passed it wont be used as is in the abstract class
     *     instead it will trigger a proper call to build the link executor
     *     from the dynamic classes loaded during command execution
     *
     * @see newTestableInstance()
     * @see newTestableInstance::createLinkExecutor()
     *
     * @return void
     */
    protected function setTestableInstanceLinkExecutorMock(?LinkExecutor $linkExecutor)
    {
        $this->instance->mockLinkExecutor = $linkExecutor;
    }
}
