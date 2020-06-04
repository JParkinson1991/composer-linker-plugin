<?php
/**
 * @file
 * BaseCommandTest.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Commands;

use Composer\Console\Application;
use Composer\EventDispatcher\EventDispatcher;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;
use JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\BaseComposerTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class BaseCommandTest
 *
 * Provides a base for command test and shareable test cases that should be
 * command execution agnostic
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Commands
 */
abstract class BaseCommandTest extends BaseComposerTestCase
{
    /**
     * @var CommandTester
     */
    protected $commandTester;

    /**
     * Returns the command instance to text
     *
     * @return mixed
     */
    abstract protected function getCommandInstance();

    /**
     * Sets up the command
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $composer = $this->getComposer();
        $composer
            ->method('getEventDispatcher')
            ->willReturn($this->createMock(EventDispatcher::class));

        $application = $this->getMockBuilder(Application::class)
            ->onlyMethods(['getComposer'])
            ->getMock();

        $application
            ->method('getComposer')
            ->willReturn($composer);

        $command = $this->getCommandInstance();
        $application->add($command);
        $command = $application->find($command->getName());

        $this->commandTester = new CommandTester($command);
    }

    /**
     * Runs the command on the command tester
     *
     * @param string[] $packageNames
     *     An array of package name arguments to pass
     *
     * @return int
     */
    protected function runCommand($packageNames = [])
    {
        // Format as expected if needed
        if (!empty($packageNames)) {
            $packageNames = [
                'package-names' => $packageNames
            ];
        }

        return $this->commandTester->execute($packageNames);
    }

    /**
     * Tests that it errors when package not installed.
     *
     * Dont initialise any packages, just run the command, composer will act
     * as if nothing is installed and this command should error
     *
     * The follow test should be command execution agnostic
     *
     * @return void
     */
    public function testItErrorsOnPackageNotInstalled(): void
    {
        $exitCode = $this->commandTester->execute([
            'package-names' => [
                'unknown/package'
            ]
        ]);

        // Assert command error
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsStringIgnoringCase(
            'failed to find package unknown/package',
            $this->commandTester->getDisplay()
        );
    }

    /**
     * Tests that it errors if a package is installed but not configured
     *
     * If someone executes a command for a package that has no config it
     * should throw an error
     *
     * The follow test should be command execution agnostic
     *
     * @return void
     */
    public function testItErrorsIfConfigNotFound(): void
    {
        $this->initialisePackage(
            'test/package',
            'test-package',
            []
        );

        $exitCode = $this->commandTester->execute([
            'package-names' => [
                'test/package'
            ]
        ]);

        // Assert command error
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsStringIgnoringCase(
            'no config found for package test/package',
            $this->commandTester->getDisplay()
        );
    }

    /**
     * Tests that the command throws error if run on a package with invalid
     * configuration
     *
     * The follow test should be command execution agnostic
     *
     * @return void
     */
    public function testItErrorsOnInvalidConfig(): void
    {
        $this->initialisePackage(
            'test/package',
            'test-package',
            []
        );

        $this->setPluginConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        'no-dir-key' => 'here'
                    ]
                ]
            ]
        ]);

        $exitCode = $this->commandTester->execute([
            'package-names' => [
                'test/package'
            ]
        ]);

        // Assert command error
        $this->assertSame(1, $exitCode);
    }
}
