<?php
/**
 * @file
 * UnlinkCommandTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Commands;

use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\UnlinkCommand;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageLocator;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;

/**
 * Class UnlinkCommandTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Commands
 */
class UnlinkCommandTest extends BaseCommandTest
{
    /**
     * Returns the command instance to text
     *
     * @return mixed
     */
    protected function getCommandInstance()
    {
        return new UnlinkCommand(new PackageLocator());
    }

    /**
     * Tests that the command unlinks all viable packages within a repository
     * when run without arguments
     *
     * @return void
     */
    public function testItUnlinksARepositoryWhenRunWithoutArguments(): void
    {
        $this->initialisePackage(
            'package/one',
            'package-one',
            ['file.txt']
        );

        $this->initialisePackage(
            'package/two',
            'package-two',
            ['file.txt']
        );

        // Create files where they should exist after link
        $this->createFiles([
            'linked-package-one/file.txt',
            'linked-package-two/file.txt'
        ]);

        $this->setPluginConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'package/one' => 'linked-package-one',
                    'package/two' => 'linked-package-two'
                ]
            ]
        ]);

        $exitCode = $this->runCommand();

        // Assert command successful
        $this->assertSame(0, $exitCode);

        // Assert none of the linked files exist after unlink
        $this->assertFileStubDoesNotExist('linked-package-one');
        $this->assertFileStubDoesNotExist('linked-package-one/file.txt');
        $this->assertFileStubDoesNotExist('linked-package-two');
        $this->assertFileStubDoesNotExist('linked-package-two/file.txt');
    }

    /**
     * Tests repository unlink errors do not break exception and viable
     * packages within the repository are attempted to be unlinked
     *
     * @return void
     */
    public function testRepositoryUnlinkErrorDontBreakProcess(): void
    {
        $this->initialisePackage(
            'package/one',
            'package-one',
            ['file.txt']
        );

        $this->initialisePackage(
            'package/two',
            'package-two',
            ['file.txt']
        );

        // Create files where they should exist after link
        $this->createFiles([
            'linked-package-one/file.txt',
            'linked-package-two/file.txt'
        ]);

        $this->setPluginConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'package/one' => 'linked-package-one',
                    'package/two' => [
                        'invalid config'
                    ]
                ]
            ]
        ]);

        $exitCode = $this->runCommand();

        // Assert command error
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsStringIgnoringCase(
            'process completed with errors',
            $this->commandTester->getDisplay()
        );

        $this->assertFileStubDoesNotExist('linked-package-one');
        $this->assertFileStubDoesNotExist('linked-package-one/file.txt');
        $this->assertFileStubExists('linked-package-two');
        $this->assertFileStubExists('linked-package-two/file.txt');
    }

    /**
     * Tests packages are unlinked when passed as arguments by name
     *
     * @return void
     */
    public function testItUnlinksPackagesPassedAsArguments()
    {
        $this->initialisePackage(
            'package/one',
            'package-one',
            ['file.txt']
        );

        $this->initialisePackage(
            'package/two',
            'package-two',
            ['file.txt']
        );

        // Create files where they should exist after link
        $this->createFiles([
            'linked-package-one/file.txt',
            'linked-package-two/file.txt'
        ]);

        $this->setPluginConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'package/one' => 'linked-package-one',
                    'package/two' => 'linked-package-two'
                ]
            ]
        ]);

        $exitCode = $this->runCommand([
            'package/one',
            'package/two'
        ]);

        // Assert command successful
        $this->assertSame(0, $exitCode);

        $this->assertFileStubDoesNotExist('linked-package-one');
        $this->assertFileStubDoesNotExist('linked-package-one/file.txt');
        $this->assertFileStubDoesNotExist('linked-package-two');
        $this->assertFileStubDoesNotExist('linked-package-two/file.txt');
    }

    /**
     * Tests that errors do not break execution when unlinking packages via
     * arguments
     *
     * @return void
     */
    public function testPackageUnlinkErrorsDoNotBreakExecution()
    {
        $this->initialisePackage(
            'package/one',
            'package-one',
            ['file.txt']
        );

        $this->initialisePackage(
            'package/two',
            'package-two',
            ['file.txt']
        );

        // Create files where they should exist after link
        $this->createFiles([
            'linked-package-one/file.txt',
            'linked-package-two/file.txt'
        ]);

        $this->setPluginConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'package/one' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'linked-package-one',
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => 'not a bool'
                        ]
                    ],
                    'package/two' => 'linked-package-two'
                ]
            ]
        ]);

        $exitCode = $this->runCommand([
            'uninstalled/package',
            'package/one',
            'package/two'
        ]);

        // Assert command error
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsStringIgnoringCase(
            'process completed with errors',
            $this->commandTester->getDisplay()
        );

        // Assert package two removed as expected
        $this->assertFileStubDoesNotExist('linked-package-two');
        $this->assertFileStubDoesNotExist('linked-package-two/file.txt');

        // Assert package one still exists as not valid
        $this->assertFileStubExists('linked-package-one');
        $this->assertFileStubExists('linked-package-one/file.txt');
    }
}
