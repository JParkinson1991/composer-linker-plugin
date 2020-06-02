<?php
/**
 * @file
 * ComposerLinkPluginTest.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Plugin;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use JParkinson1991\ComposerLinkerPlugin\Composer\Plugin\ComposerLinkerPlugin;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;
use JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\BaseComposerTestCase;
use RuntimeException;

/**
 * Class ComposerLinkPluginTest

 * Whilst this test class is intended to be a full end to end test of this
 * plugin we must still mock what we do not have control over. Mocking
 * covers the 'composer' environment.
 *
 * None of the functionality provided by this plugin is mocked during the test
 * cases in this class.
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Plugin
 */
class ComposerLinkerPluginTest extends BaseComposerTestCase
{
    /**
     * The name of the test package that can be used in test cases of this class
     */
    public const TEST_PACKAGE_NAME = 'test/package';

    /**
     * Mocked test package object with name TEST_PACKAGE_NAME
     *
     * @var \Composer\Package\PackageInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $package;

    /**
     * Sets up the test case
     *
     * Initialise a default test package
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->package = $this->initialisePackage(
            self::TEST_PACKAGE_NAME,
            'test/package',
            [
                'README.md',
                'src/Class.php',
                'src/Services/Service.php'
            ]
        );
    }

    /**
     * Tests that the plugin links packages as expected
     *
     * @dataProvider dataProviderLinking
     *
     * @param array $pluginConfig
     *     The plugin config to test
     * @param string[] $expectFileExists
     *     Expected files to exist
     * @param string[] $expectFileNotExists
     *     Expected files to not exists
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function testItLinksAPackage(
        array $pluginConfig,
        array $expectFileExists,
        array $expectFileNotExists
    ): void {
        // Configure and run the plugin using the provided config
        $this->setPluginConfig($pluginConfig);
        $this->runPlugin('link');

        // Determine whether symlink was used in the plugin configs
        // phpcs:disable Generic.Files.LineLength.TooLong
        if (isset($pluginConfig[LinkDefinitionFactory::CONFIG_KEY_ROOT][LinkDefinitionFactory::CONFIG_KEY_LINKS][self::TEST_PACKAGE_NAME][LinkDefinitionFactory::CONFIG_KEY_OPTIONS][LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY])) {
            $usesSymlink = !($pluginConfig[LinkDefinitionFactory::CONFIG_KEY_ROOT][LinkDefinitionFactory::CONFIG_KEY_LINKS][self::TEST_PACKAGE_NAME][LinkDefinitionFactory::CONFIG_KEY_OPTIONS][LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY]);
        }
        elseif (isset($pluginConfig[LinkDefinitionFactory::CONFIG_KEY_ROOT][LinkDefinitionFactory::CONFIG_KEY_OPTIONS][LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY])) {
            $usesSymlink = !($pluginConfig[LinkDefinitionFactory::CONFIG_KEY_ROOT][LinkDefinitionFactory::CONFIG_KEY_OPTIONS][LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY]);
        }
        else {
            $usesSymlink = true;
        }
        // phpcs:enable Generic.Files.LineLength.TooLong

        // Determine if plugin config for directory or file linking
        // If directory link, check it exsist and $expectSymlink, dont check files
        // If file link, dont check dir, check files for existence and $expectSymlink
        // phpcs:disable Generic.Files.LineLength.TooLong
        if (empty($pluginConfig[LinkDefinitionFactory::CONFIG_KEY_ROOT][LinkDefinitionFactory::CONFIG_KEY_LINKS][self::TEST_PACKAGE_NAME][LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES])) {
            // No files defined, full directory link, determine destination dir
            $destinationDir = is_string($pluginConfig[LinkDefinitionFactory::CONFIG_KEY_ROOT][LinkDefinitionFactory::CONFIG_KEY_LINKS][self::TEST_PACKAGE_NAME])
                ? $pluginConfig[LinkDefinitionFactory::CONFIG_KEY_ROOT][LinkDefinitionFactory::CONFIG_KEY_LINKS][self::TEST_PACKAGE_NAME]
                : $pluginConfig[LinkDefinitionFactory::CONFIG_KEY_ROOT][LinkDefinitionFactory::CONFIG_KEY_LINKS][self::TEST_PACKAGE_NAME][LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR];

            // Assert linked dir exists
            // Assert is as $expectSymlink
            $this->assertFileStubExists($destinationDir);
            if ($usesSymlink) {
                $this->assertFileStubIsSymlink($destinationDir);
            }
            else {
                $this->assertFileStubIsNotSymlink($destinationDir);
            }

            // Dont check symlink status against files
            $checkFileLinks = false;
        }
        else {
            // Plugin config contains specific file mappings, check the file
            // links against $expectSymlink
            $checkFileLinks = true;
        }
        // phpcs:enable Generic.Files.LineLength.TooLong

        // Ensure every expected file exists
        foreach ($expectFileExists as $expectedFileStub) {
            $this->assertFileStubExists($expectedFileStub);

            if ($checkFileLinks === true) {
                if ($usesSymlink) {
                    $this->assertFileStubIsSymlink($expectedFileStub);
                }
                else {
                    $this->assertFileStubIsNotSymlink($expectedFileStub);
                }
            }
        }

        // Ensure every expected file does not exist
        foreach ($expectFileNotExists as $notExpectedFileStub) {
            $this->assertFileStubDoesNotExist($notExpectedFileStub);
        }
    }

    /**
     * Tests that the plugin is able to unlink a package as expected
     *
     * @dataProvider dataProviderUnlink
     *
     * @param array $pluginConfig
     *     The plugin config to test
     * @param string[] $extraFiles
     *     Any extra files to create within the linked package file structure.
     *     These files created outside of the knowledge of the plugin, useful
     *     when testing orphan cleanup.
     *     Provide stubs starting at plugin destination dir. Resolved to
     *     absolute by test case.
     * @param string[] $expectFileNotExists
     *     Expected files to not exist after unlinking with the given config
     *     Provide stubs starting at plugin destination dir. Resolved to
     *     absolute by test case.
     * @param string[] $expectFileExists
     *     Expected files to still exist after unlinking with the given config.
     *     Provide stubs starting at plugin destination dir. Resolved to
     *     absolute by test case.
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function testItUnlinksAPackage(
        array $pluginConfig,
        array $extraFiles,
        array $expectFileNotExists,
        array $expectFileExists
    ): void {
        // Configure and run the plugin creating the link file structure
        $this->setPluginConfig($pluginConfig);
        $this->runPlugin('link');

        // Add extra files into link dir as needed
        // Useful when testing orphan cleanup
        $this->createFiles($extraFiles);

        // Unlink the plugin
        $this->runPlugin('unlink');

        // Check all of the expected non existent files do not exist
        foreach ($expectFileNotExists as $expectFileNotExistStub) {
            $this->assertFileStubDoesNotExist($expectFileNotExistStub);
        }

        // Check all of the expected existing files still exists.
        foreach ($expectFileExists as $expectedFileExistsStub) {
            $this->assertFileStubExists($expectedFileExistsStub);
        }
    }

    /**
     * Tests that the plugin initialises itself after it is installed by
     * running link against any defined config that existed prior to
     * installation.
     *
     * For example, a user writing plugin config before requiring it in their
     * project.
     *
     * @return void
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function testItInitialisesItselfAfterInstall(): void
    {
        $this->initialisePackage(
            'another/test',
            'another-test',
            [
                'src/file1.txt',
                'src/file2.txt'
            ]
        );

        // Configure the plugin
        // Do simple link for standard test package
        // Do specific file link for custom test package created in this test case
        $this->setPluginConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    self::TEST_PACKAGE_NAME => 'linked-test-package',
                    'another/test' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'linked-another-test',
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                            'src/file1.txt' => 'linked-file1.txt'
                        ]
                    ]
                ]
            ]
        ]);

        // Create a mock package for the plugin
        $pluginPackage = $this->createMock(PackageInterface::class);
        $pluginPackage
            ->method('getName')
            ->willReturn('jparkinson1991/composer-linker-plugin');

        // Run the init method with the plugin package
        $this->runPlugin('init', $pluginPackage);

        // Expect all standard $this->package files to exist in linked dir
        $this->assertFileStubExists('linked-test-package');
        $this->assertFileStubExists('linked-test-package/README.md');
        $this->assertFileStubExists('linked-test-package/src');
        $this->assertFileStubExists('linked-test-package/src/Class.php');
        $this->assertFileStubExists('linked-test-package/src/Services');
        $this->assertFileStubExists('linked-test-package/src/Services/Service.php');

        // Expect only the one file existed with new name for $anotherTestPackage
        $this->assertFileStubExists('linked-another-test');
        $this->assertFileStubExists('linked-another-test/linked-file1.txt');

        // Ensure standard files not carried over for $anotherTestPackage
        $this->assertFileStubDoesNotExist('linked-another-test/src');
        $this->assertFileStubDoesNotExist('linked-another-test/src/file1.txt');
        $this->assertFileStubDoesNotExist('linked-another-test/src/file2.txt');
    }

    /**
     * Tests that when this plugin is uninstalled it cleans up all previously
     * linked package files.
     *
     * @return void
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function testItCleansUpAfterPluginUninstall(): void
    {
        $this->initialisePackage(
            'package/one',
            'package-one',
            [
                'file.txt'
            ]
        );

        $this->initialisePackage(
            'package/two',
            'package-two',
            [
                'file.txt'
            ]
        );

        // Configure the plugin
        $this->setPluginConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'package/one' => 'linked-package-one',
                    'package/two' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'linked-package-two'
                    ]
                ]
            ]
        ]);

        // Mimick an already existing linked context
        $this->createFiles([
            'linked-package-one/file.txt',
            'linked-package-two/file.txt'
        ]);

        // Assert everything exists as expected
        $this->assertFileStubExists('package-one', $this->vendorDirectory);
        $this->assertFileStubExists('package-one/file.txt', $this->vendorDirectory);
        $this->assertFileStubExists('package-two', $this->vendorDirectory);
        $this->assertFileStubExists('package-two/file.txt', $this->vendorDirectory);
        $this->assertFileStubExists('linked-package-one');
        $this->assertFileStubExists('linked-package-one/file.txt');
        $this->assertFileStubExists('linked-package-two');
        $this->assertFileStubExists('linked-package-two/file.txt');

        // Create a mock package for the plugin
        $pluginPackage = $this->createMock(PackageInterface::class);
        $pluginPackage
            ->method('getName')
            ->willReturn('jparkinson1991/composer-linker-plugin');

        // Run the cleanup action on the plugin
        $this->runPlugin('cleanup', $pluginPackage);

        // Ensure non of the linked files exist anymore
        $this->assertFileStubDoesNotExist('linked-package-one');
        $this->assertFileStubDoesNotExist('linked-package-one/file.txt');
        $this->assertFileStubDoesNotExist('linked-package-two');
        $this->assertFileStubDoesNotExist('linked-package-two/file.txt');
    }

    /**
     * Provides data for the plugin linking test
     *
     * @return array[]
     *     An array of parameter sets where each element in the set is:
     *         - array: plugin configuration
     *         - array: expected files to exist
     *                  Use stubs starting at plugin destination dir
     *                  Absolute path resolved by test case
     *         - array: expected files to not exists
     *                  Use stubs starting at plugin destination dir
     *                  Absolute path resolved by test case
     */
    public function dataProviderLinking(): array
    {
        return [
            'linked dir - symlinked' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            self::TEST_PACKAGE_NAME => 'linked-package'
                        ]
                    ]
                ],
                [
                    'linked-package/README.md',
                    'linked-package/src/Class.php',
                    'linked-package/src/Services/Service.php'
                ],
                [
                    // No files expected to not exist
                ]
            ],
            'linked dir - copied - global option' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            self::TEST_PACKAGE_NAME => 'linked-package'
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true
                        ]
                    ]
                ],
                [
                    'linked-package/README.md',
                    'linked-package/src/Class.php',
                    'linked-package/src/Services/Service.php'
                ],
                [
                    // No files expected to not exist
                ]
            ],
            'linked dir - symlink - package option override' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            self::TEST_PACKAGE_NAME => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'linked-package',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => false
                                ]
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true
                        ]
                    ]
                ],
                [
                    'linked-package/README.md',
                    'linked-package/src/Class.php',
                    'linked-package/src/Services/Service.php'
                ],
                [
                    // No files expected to not exist
                ]
            ],
            'linked dir - copied - package option override' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            self::TEST_PACKAGE_NAME => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'linked-package',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true
                                ]
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => false
                        ]
                    ]
                ],
                [
                    'linked-package/README.md',
                    'linked-package/src/Class.php',
                    'linked-package/src/Services/Service.php'
                ],
                [
                    // No files expected to not exist
                ]
            ],
            'linked files - symlink' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            self::TEST_PACKAGE_NAME => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'linked-package',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'src/Services/Service.php'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'linked-package/src/Services/Service.php'
                ],
                [
                    'linked-package/README.md',
                    'linked-package/src/Class.php'
                ]
            ],
            'linked files - copied' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            self::TEST_PACKAGE_NAME => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'linked-package',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'src/Services/Service.php',
                                    'README.md'
                                ],
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'linked-package/src/Services/Service.php',
                    'linked-package/README.md'
                ],
                [
                    'linked-package/src/Class.php'
                ]
            ]
        ];
    }

    /**
     * Provides data for the plugin unlinking test
     *
     * @return array[]
     *     An array of parameter sets where each element in the set is:
     *         - array: plugin configuration
     *         - array: extra files to create in link file structure outside
     *                  of plugin's knowledge. Useful when testing orphan
     *                  cleanup.
     *                  Use stubs starting at plugin destination dir
     *                  Absolute path resolved by test case
     *         - array: expected files to not exist after unlink
     *                  Use stubs starting at plugin destination dir
     *                  Absolute path resolved by test case
     *         - array: expected files to still exist after unlink
     *                  Use stubs starting at plugin destination dir
     *                  Absolute path resolved by test case
     */
    public function dataProviderUnlink(): array
    {
        return [
            'linked dir - no orphan cleanup' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            self::TEST_PACKAGE_NAME => 'linked-package'
                        ]
                    ]
                ],
                [
                    // Dont create any extra files
                ],
                [
                    'linked-package'
                ],
                [
                    // Dont check existence of any files
                ]
            ],
            'linked dir - oprhan cleanup' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            self::TEST_PACKAGE_NAME => 'linked-package'
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => true
                        ]
                    ]
                ],
                [
                    // Dont create any extra files
                ],
                [
                    'linked-package'
                ],
                [
                    // Dont check existence of any files
                ]
            ],
            'linked files' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            self::TEST_PACKAGE_NAME => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'linked-package',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'src/Services/Service.php',
                                    'src/Class.php'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    // Dont create any extra files
                ],
                [
                    'linked-package/src/Services/Service.php',
                    'linked-package/src/Class.php'
                ],
                [
                    'linked-package/src/Services',
                    'linked-package/src',
                    'linked-package'
                ]
            ],
            'linked files - orphan cleanup' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            self::TEST_PACKAGE_NAME => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'linked-package',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'src/Services/Service.php',
                                    'src/Class.php'
                                ],
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => true
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    // Dont create any extra files
                ],
                [
                    // Check files dont exists
                    'linked-package/src/Services/Service.php',
                    'linked-package/src/Services',
                    'linked-package/src/Class.php',
                    'linked-package/src',
                    'linked-package'
                ],
                [
                    // No files to check existence of
                ]
            ],
            'linked files - orphan cleanup with non empty dirs' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            self::TEST_PACKAGE_NAME => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'linked-package',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'src/Services/Service.php',
                                    // phpcs:ignore
                                    'src/Class.php' => [
                                        'src/Class.php',
                                        'branch-1/Class.php',
                                        'branch-2/nested/dirs/Class.php'
                                    ]
                                ],
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => true
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    // Create extra files that should stop orphan clean up
                    'linked-package/src/Services/test.txt',
                    'linked-package/branch-2/nested/test.txt'
                ],
                [
                    // Check files dont exists
                    'linked-package/src/Services/Service.php',
                    'linked-package/src/Class.php',
                    'linked-package/branch-1/Class.php',
                    'linked-package/branch-1',
                    'linked-package/branch-2/nested/dirs/Class.php',
                    'linked-package/branch-2/nested/dirs'
                ],
                [
                    // These file should exist after unlink with orphan cleanup
                    'linked-package/src/Services/test.txt',
                    'linked-package/src/Services',
                    'linked-package/src',
                    'linked-package/branch-2/nested/test.txt',
                    'linked-package/branch-2/nested',
                    'linked-package/branch-2',
                    'linked-package'
                ]
            ]
        ];
    }

    /**
     * Runs the given action on the plugin
     *
     * This method handles mock creation/configuration of events that are
     * passed to the plugin to run the relevant action.
     *
     * @param string $action
     *     Either link, or unlink
     *
     * @param \Composer\Package\PackageInterface|null $package
     *     The package to run against the plugin
     *     If not provided, the default $this->package will be used
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     * @throws \Exception
     */
    protected function runPlugin(string $action, PackageInterface $package = null): void
    {
        if ($package === null) {
            $package = $this->package;
        }

        // Create the plugin, using class property composer configured and
        // ready to use test project package etc
        $composerLinkerPlugin = new ComposerLinkerPlugin();
        $composerLinkerPlugin->activate(
            $this->getComposer(),
            $this->createMock(IOInterface::class)
        );

        // Create the relevant composer operation for the action
        // Have it return the test package
        $operation = $this->createMock(
            ($action === 'link')
                ? InstallOperation::class
                : UninstallOperation::class
        );
        $operation
            ->method('getPackage')
            ->willReturn($package);

        // Create the package event, have it return the package containing
        // operation as well as the mocked composer etc
        $event = $this->createMock(PackageEvent::class);
        $event
            ->method('getIO')
            ->willReturn($this->createMock(IOInterface::class));
        $event
            ->method('getComposer')
            ->willReturn($this->getComposer());
        $event
            ->method('getOperation')
            ->willReturn($operation);

        switch ($action) {
            case 'link':
                $composerLinkerPlugin->linkPackageFromEvent($event);
                break;
            case 'unlink':
                $composerLinkerPlugin->unlinkPackageFromEvent($event);
                break;
            case 'init':
                $composerLinkerPlugin->initPlugin($event);
                break;
            case 'cleanup':
                $composerLinkerPlugin->cleanUpPlugin($event);
                break;
            default:
                throw new RuntimeException('what chu talkin bout willis');
        }
    }
}
