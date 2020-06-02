<?php
/**
 * @file
 * LinkCommandTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Commands;

use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\LinkCommand;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageLocator;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;

/**
 * Class LinkCommandTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Commands
 */
class LinkCommandTest extends BaseCommandTest
{
    /**
     * Returns the command instance to text
     *
     * @return mixed
     */
    protected function getCommandInstance()
    {
        return new LinkCommand(new PackageLocator());
    }

    /**
     * Tests that the command links all viable packages within a repository
     * when run without arguments
     *
     * @return void
     */
    public function testItLinksARepositoryWhenRunWithoutArguments(): void
    {
        $this->initialisePackage(
            'package/one',
            'package-one',
            [
                'README.md',
                'src/Class1.php'
            ]
        );

        $this->initialisePackage(
            'package/two',
            'package-two',
            [
                'README.md',
                'src/Class2.php'
            ]
        );

        $this->initialisePackage(
            'package/three',
            'package-three',
            [
                'README.md',
                'src/Class3.php'
            ]
        );

        $this->setPluginConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'package/one' => 'linked-package-one',
                    'package/two' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'linked-package-two',
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                            'src/Class2.php'
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true
                        ]
                    ]
                ]
            ]
        ]);

        $exitCode = $this->runCommand();

        // Assert command successful
        $this->assertSame(0, $exitCode);

        // Assert package one link existing
        $this->assertFileStubExists('linked-package-one');
        $this->assertFileStubIsSymlink('linked-package-one');
        $this->assertFileStubExists('linked-package-one/README.md');
        $this->assertFileStubExists('linked-package-one/src/Class1.php');

        // Asset package two link existing
        $this->assertFileStubExists('linked-package-two');
        $this->assertFileStubExists('linked-package-two/src/Class2.php');
        $this->assertFileStubIsNotSymlink('linked-package-two/src/Class2.php');
        $this->assertFileStubDoesNotExist('linked-package-two/README.md');

        // Assert package three not linked
        $this->assertFileStubDoesNotExist('linked-package-three');
    }

    /**
     * Tests that when the command is run without arguments thus linking the
     * entire repository that errors do not break execution, but are still
     * handled and sent to the caller
     *
     * @return void
     */
    public function testRepositoryLinkErrorsDontBreakProcessButOutputAfter(): void
    {
        $this->initialisePackage(
            'package/one',
            'package-one',
            []
        );

        $this->initialisePackage(
            'package/two',
            'package-two',
            []
        );

        $this->initialisePackage(
            'package/three',
            'package-three',
            []
        );

        $this->setPluginConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'package/one' => 'linked-package-one',
                    'package/two' => [
                        'invalid-config' => 'here'
                    ],
                    'package/three' => 'linked-package-three'
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

        // Assert valid packages linked
        $this->assertFileStubExists('linked-package-one');
        $this->assertFileStubExists('linked-package-three');
    }

    /**
     * Tests that packages passed as arguments by name
     *
     * @return void
     */
    public function testItLinksPackagesPassedAsArguments(): void
    {
        $this->initialisePackage(
            'package/one',
            'package-one',
            []
        );

        $this->initialisePackage(
            'package/two',
            'package-two',
            []
        );

        $this->initialisePackage(
            'package/three',
            'package-three',
            []
        );

        $this->setPluginConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'package/one' => 'linked-package-one',
                    'package/two' => 'linked-package-two',
                    'package/three' => 'linked-package-three'
                ]
            ]
        ]);

        $exitCode = $this->runCommand([
            'package/one',
            'package/three'
        ]);

        // Assert command successful
        $this->assertSame(0, $exitCode);

        // Assert package one files exist
        $this->assertFileStubExists('linked-package-one');

        // Assert package two files dont exist despite plugin config existing
        // Command when given arguments should only link those files
        $this->assertFileStubDoesNotExist('linked-package-two');

        // Assert package three files exist
        $this->assertFileStubExists('linked-package-three');
    }

    /**
     * Test that package link error do not break execution.
     *
     * If multiple arguments are passed and some trigger errors still attempt
     * to process the valid arguments.
     *
     * @void
     */
    public function testPackageLinkErrorsDontBreakExecution(): void
    {
        $this->initialisePackage(
            'package/one',
            'package-one',
            []
        );

        $this->initialisePackage(
            'package/two',
            'package-two',
            []
        );

        $this->setPluginConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'package/one' => 'linked-package-one',
                    'package/two' => [
                        'invalid-config'
                    ]
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

        // Assert valid package linked
        $this->assertFileStubExists('linked-package-one');
    }
}
