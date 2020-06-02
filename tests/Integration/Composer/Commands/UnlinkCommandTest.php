<?php
/**
 * @file
 * UnlinkCommandTestWithDataProvider.php
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
     * @dataProvider commandDataProvider()
     *
     * @param array[] $packages
     *     An array of package definition arrays where each sub array value
     *     at index =
     *         0: package name string
     *         1: package install dir stub string
     *         2: package file stubs string[]
     * @param string[] $createFiles
     *     File stubs to create per test case
     * @param array $pluginConfig
     *     The plugin config array
     * @param array $packageNameArgs
     *     An array of arguments to pass to the command when executed
     * @param int $expectedExitCode
     *     The expected exit code of the command
     * @param string|null $expectDisplayContains
     *     Expect command display to contain
     *     Skip this check by passing null value
     * @param string[] $expectFileStubNotExist
     *     Flat array of file stubs to check non-existence of
     * @param string[] $expectFileStubExists
     *     Flat array of file stubs to check existence of
     *
     * @return void
     */
    public function testCommand(
        array $packages,
        array $createFiles,
        array $pluginConfig,
        array $packageNameArgs,
        int $expectedExitCode,
        ?string $expectDisplayContains,
        array $expectFileStubNotExist,
        array $expectFileStubExists
    ): void {
        // Process each package definition initialising it and its file
        foreach ($packages as [$packageName, $packageInstallDir, $packageFiles]) {
            $this->initialisePackage($packageName, $packageInstallDir, $packageFiles);
        }

        // Create all requires files from path stubs
        $this->createFiles($createFiles);

        // Set plugin config
        $this->setPluginConfig($pluginConfig);

        $exitCode = $this->runCommand($packageNameArgs);

        // Check exit code and display as necessary
        $this->assertSame($expectedExitCode, $exitCode);
        if ($expectDisplayContains !== null) {
            $this->assertStringContainsStringIgnoringCase(
                $expectDisplayContains,
                $this->commandTester->getDisplay()
            );
        }

        foreach ($expectFileStubNotExist as $stub) {
            $this->assertFileStubDoesNotExist($stub);
        }

        foreach ($expectFileStubExists as $stub) {
            $this->assertFileStubExists($stub);
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
            'unlink repository - no args' => [
                [
                    // Mock package configs
                    // Subarray - Name, install path, files
                    [
                        'package/one',
                        'package-one',
                        [
                            'file.txt'
                        ]
                    ],
                    [
                        'package/two',
                        'package-two',
                        [
                            'file.txt'
                        ]
                    ]
                ],
                [
                    // Create files
                    'linked-package-one/file.txt',
                    'linked-package-two/file.txt'
                ],
                [
                    // Plugin config
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'package/one' => 'linked-package-one',
                            'package/two' => 'linked-package-two'
                        ]
                    ]
                ],
                [
                    // no package name args
                ],
                0, // exit code
                'process completed', // command out,
                [
                    // stub not exist
                    'linked-package-one',
                    'linked-package-one/file.txt',
                    'linked-package-two',
                    'linked-package-two/file.txt'
                ],
                [
                    // dont check for files to exist
                ]
            ],
            'unlink repository - skip errors' => [
                [
                    // Mock package configs
                    // Subarray - Name, install path, files
                    [
                        'package/one',
                        'package-one',
                        [
                            'file.txt'
                        ]
                    ],
                    [
                        'package/two',
                        'package-two',
                        [
                            'file.txt'
                        ]
                    ]
                ],
                [
                    // Create files
                    'linked-package-one/file.txt',
                    'linked-package-two/file.txt'
                ],
                [
                    // Plugin config
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'package/one' => 'linked-package-one',
                            'package/two' => [
                                'invalid config'
                            ]
                        ]
                    ]
                ],
                [
                    // no command args
                ],
                1, // exit code
                'process completed with errors', // command output,
                [
                    // check files not exist
                    'linked-package-one',
                    'linked-package-one/file.txt'
                ],
                [
                    // Check files do exist
                    'linked-package-two',
                    'linked-package-two/file.txt'
                ]
            ],
            'unlink packages - with args' => [
                [
                    // Mock package configs
                    // Subarray - Name, install path, files
                    [
                        'package/one',
                        'package-one',
                        [
                            'file.txt'
                        ]
                    ],
                    [
                        'package/two',
                        'package-two',
                        [
                            'file.txt'
                        ]
                    ]
                ],
                [
                    // Create files
                    'linked-package-one/file.txt',
                    'linked-package-two/file.txt'
                ],
                [
                    // Plugin config
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'package/one' => 'linked-package-one',
                            'package/two' => 'linked-package-two'
                        ]
                    ]
                ],
                [
                    // Command args
                    'package/one',
                    'package/two'
                ],
                0, // Exit code
                'process completed', // Command output
                [
                    // Expect not exist
                    'linked-package-one',
                    'linked-package-one/file.txt',
                    'linked-package-two',
                    'linked-package-two/file.txt'
                ],
                [
                    // Dont expect any files to exist
                ]
            ],
            'unlink packages - with args - skip errors' => [
                [
                    // Mock package configs
                    // Subarray - Name, install path, files
                    [
                        'package/one',
                        'package-one',
                        [
                            'file.txt'
                        ]
                    ],
                    [
                        'package/two',
                        'package-two',
                        [
                            'file.txt'
                        ]
                    ]
                ],
                [
                    // Create files
                    'linked-package-one/file.txt',
                    'linked-package-two/file.txt'
                ],
                [
                    // Plugin config
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
                ],
                [
                    // Command args
                    'uninstalled/package',
                    'package/one',
                    'package/two'
                ],
                1, // Exit code
                'process completed with errors', // Command output
                [
                    // Expect not exists
                    'linked-package-two',
                    'linked-package-two/file.txt'
                ],
                [
                    // Expect exist
                    'linked-package-one',
                    'linked-package-one/file.txt'
                ]
            ]
        ];
    }
}
