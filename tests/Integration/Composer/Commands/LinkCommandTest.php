<?php
/**
 * @file
 * LinkProviderTestWithDataProvider.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Commands;

use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\LinkCommand;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageLocator;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;

/**
 * Class LinkProviderTest
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
     * Tests the execution and outputs of the link command
     *
     * @dataProvider commandDataProvider
     *
     * @param array[] $packages
     *     An array of package definition arrays where each sub array value
     *     at index =
     *         0: package name string
     *         1: package install dir stub string
     *         2: package file stubs string[]
     * @param array $pluginConfig
     *     The plugin config array
     * @param array $packageNameArgs
     *     An array of arguments to pass to the command when executed
     * @param int $expectedExitCode
     *     The expected exit code of the command
     * @param string|null $expectDisplayContains
     *     Expect command display to contain
     *     Skip this check by passing null value
     * @param string[] $expectStubExists
     *     Flat array of file stubs to check existence of
     * @param string[] $expectStubDoesNotExist
     *     Flat array of file stubs to check non-existence of
     * @param array $expectStubIsSymlink
     *     Flat array of file stubs to check if symlink
     * @param array $expectStubIsNotSymlink
     *     Flat array of file stubs to check not symlink
     *
     * @return void
     */
    public function testCommand(
        array $packages,
        array $pluginConfig,
        array $packageNameArgs,
        int $expectedExitCode,
        ?string $expectDisplayContains,
        array $expectStubExists = [],
        array $expectStubDoesNotExist = [],
        array $expectStubIsSymlink = [],
        array $expectStubIsNotSymlink = []
    ): void {
        // Process each package definition initialising it and its file
        foreach ($packages as [$packageName, $packageInstallDir, $packageFiles]) {
            $this->initialisePackage($packageName, $packageInstallDir, $packageFiles);
        }

        // Set plugin config
        $this->setPluginConfig($pluginConfig);

        // Run command with args capturing exit code
        $exitCode = $this->runCommand($packageNameArgs);

        // Check exit code and display as necessary
        $this->assertSame($expectedExitCode, $exitCode);
        if ($expectDisplayContains !== null) {
            $this->assertStringContainsStringIgnoringCase(
                $expectDisplayContains,
                $this->commandTester->getDisplay()
            );
        }

        foreach ($expectStubExists as $stub) {
            $this->assertFileStubExists($stub);
        }

        foreach ($expectStubDoesNotExist as $stub) {
            $this->assertFileStubDoesNotExist($stub);
        }

        foreach ($expectStubIsSymlink as $stub) {
            $this->assertFileStubIsSymlink($stub);
        }

        foreach ($expectStubIsNotSymlink as $stub) {
            $this->assertFileStubIsNotSymlink($stub);
        }
    }

    /**
     * Provides data for the command test
     *
     * @return array|array[]
     *     Detailed structure of data provided in test method param statements
     */
    public function commandDataProvider(): array
    {
        return [
            'link repository - no args' => [
                [
                    // Mock package configs
                    // Subarray - Name, install path, files
                    [
                        'package/one',
                        'package-one',
                        [
                            'README.md',
                            'src/Class1.php'
                        ]
                    ],
                    [
                        'package/two',
                        'package-two',
                        [
                            'README.md',
                            'src/Class2.php'
                        ]
                    ],
                    [
                        'package/three',
                        'package-three',
                        [
                            'README.md',
                            'src/Class3.php'
                        ]
                    ]
                ],
                [
                    // Plugin configuration
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
                ],
                [
                    // No package name arguments
                ],
                0, // Expected exit code
                'process completed', // Expect output contains
                [
                    // Expect file stubs exist
                    'linked-package-one',
                    'linked-package-one/README.md',
                    'linked-package-one/src/Class1.php',
                    'linked-package-two',
                    'linked-package-two/src/Class2.php'
                ],
                [
                    // Expect file stubs dont exist
                    'linked-package-two/README.md',
                    'linked-package-three',
                    'linked-package-three/README.md',
                    'linked-package-three/src/Class3.php'
                ],
                [
                    // Expect symlinks
                    'linked-package-one'
                ],
                [
                    //Expect not symlinks
                    'linked-package-two/src/Class2.ph'
                ]
            ],
            'link repository - has skippable errors' => [
                [
                    // Mock package configs
                    // Subarray - Name, install path, files
                    ['package/one', 'package-one', []],
                    ['package/two', 'package-two', []],
                    ['package/three', 'package-three', []]
                ],
                [
                    // Plugin config
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'package/one' => 'linked-package-one',
                            'package/two' => [
                                'invalid-config' => 'here'
                            ],
                            'package/three' => 'linked-package-three'
                        ]
                    ]
                ],
                [
                    // No package name arguments
                ],
                1, // Expected exit code
                'process completed with errors', // Expect output contains,
                [
                    // Expect file stubs exist
                    'linked-package-one',
                    'linked-package-three'
                ],
                [
                    // Dont check for files not to exist
                ],
                [
                    // Dont check for symlinks
                ],
                [
                    //Dont check non symlinks
                ]
            ],
            'link packages - with arguments' => [
                [
                    // Mock package configs
                    // Subarray - Name, install path, files
                    ['package/one', 'package-one', []],
                    ['package/two', 'package-two', []],
                    ['package/three', 'package-three', []]
                ],
                [
                    // Plugin config
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'package/one' => 'linked-package-one',
                            'package/two' => 'linked-package-two',
                            'package/three' => 'linked-package-three'
                        ]
                    ]
                ],
                [
                    // Command arguments
                    'package/one',
                    'package/three'
                ],
                0, // Exit code
                'process completed', // statue message
                [
                    // Stubs exist
                    'linked-package-one',
                    'linked-package-three'
                ],
                [
                    // Stubs not exist
                    'linked-package-two'
                ],
                [
                    // Dont check for symlinks
                ],
                [
                    //Dont check non symlinks
                ]
            ],
            'link packages - with argument - skip errors' => [
                [
                    // Mock package configs
                    // Subarray - Name, install path, files
                    ['package/one', 'package-one', []],
                    ['package/two', 'package-two', []]
                ],
                [
                    // Plugin config
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'package/one' => 'linked-package-one',
                            'package/two' => [
                                'invalid-config'
                            ]
                        ]
                    ]
                ],
                [
                    // Command arguments
                    'uninstalled/package',
                    'package/one',
                    'package/two'
                ],
                1, // exit code
                'process completed with errors', // status message
                [
                    // Stub exist
                    'linked-package-one'
                ],
                [
                    // Dont check stubs not exist
                ],
                [
                    // Dont check for symlinks
                ],
                [
                    //Dont check non symlinks
                ]
            ]
        ];
    }
}
