<?php
/**
 * @file
 * ComposerLinkPluginTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Plugin;

use Composer\Composer;
use Composer\Config;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackage;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use InvalidArgumentException;
use JParkinson1991\ComposerLinkerPlugin\Composer\Plugin\ComposerLinkerPlugin;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

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
class ComposerLinkPluginTest extends TestCase
{
    /**
     * The name of the test package that can be used in test cases of this class
     */
    public const TEST_PACKAGE_NAME = 'test/package';

    /**
     * Mocked composer object used for plugin activation
     *
     * @var \Composer\Composer|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $composer;

    /**
     * Mocked installation manager
     *
     * @var \Composer\Installer\InstallationManager|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $installationManager;

    /**
     * Mocked test package object with name TEST_PACKAGE_NAME
     *
     * @var \Composer\Package\PackageInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $package;

    /**
     * Holds what will be treat as the project root path during these tests
     *
     * @var string
     */
    protected $projectRootPath;


    protected $repositoryManager;

    /**
     * Mock root package object, holds plugin config.
     *
     * Instantiated during setup, configured per test class
     *
     * @var \Composer\Package\RootPackage|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $rootPackage;

    /**
     * Prepares the environment before running a test case
     *
     * @return void
     */
    public function setUp(): void
    {
        // Create and store a project root path
        $this->projectRootPath = (new \Composer\Util\Filesystem())->normalizePath(
            __DIR__.'../../../../../var/composer_plugin_test_root'
        );

        // Left over project root path, delete it
        if (file_exists($this->projectRootPath)) {
            $this->tearDown();
        }

        // Create a mock composer config instance so when called to get the
        // vendor it will return our mock project root. Not a class property
        // as never needed to be configured against
        $config = $this->createMock(Config::class);
        $config
            ->method('get')
            ->with('vendor-dir')
            ->willReturn($this->projectRootPath.'/vendor');

        // Create a mock package, uses this throughout these test cases
        // Class property so it can be reused per test case
        $this->package = $this->createMock(PackageInterface::class);
        $this->package
            ->method('getName')
            ->willReturn(self::TEST_PACKAGE_NAME);

        // Create a mock installation manager that will return the a known
        // installation path within the test project root. Not class property
        // doesnt need to be used again
        $this->installationManager = $this->createMock(InstallationManager::class);
        $this->installationManager
            ->method('getInstallPath')
            ->with($this->package)
            ->willReturn($this->projectRootPath.'/vendor/test/package');

        // Create a configurable mock repository manager
        $this->repositoryManager = $this->createMock(RepositoryManager::class);

        // Some functionality outside of the control of this package must
        // still be mocked. Class property to avoid per test case configuration.
        // No plugin config done here, can be handle per test case using the
        // configurePlugin() method
        $this->rootPackage = $this->createMock(RootPackage::class);

        // Create the composer object mock returning all of the mocks created
        $this->composer = $this->createMock(Composer::class);
        $this->composer
            ->method('getConfig')
            ->willReturn($config);
        $this->composer
            ->method('getInstallationManager')
            ->willReturn($this->installationManager);
        $this->composer
            ->method('getPackage')
            ->willReturn($this->rootPackage);
        $this->composer
            ->method('getRepositoryManager')
            ->willReturn($this->repositoryManager);

        // Create all the required files
        $fileSystem = new Filesystem();
        $fileSystem->mkdir($this->projectRootPath);
        $fileSystem->mkdir($this->projectRootPath.'/vendor/test/package/src/Services');
        $fileSystem->touch([
            $this->projectRootPath.'/vendor/test/package/README.md',
            $this->projectRootPath.'/vendor/test/package/src/Class.php',
            $this->projectRootPath.'/vendor/test/package/src/Services/Service.php'
        ]);
    }

    /**
     * Tears down the environment after running a test case
     *
     * @return void
     */
    public function tearDown(): void
    {
        (new FileSystem())->remove($this->projectRootPath);
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
     */
    public function testItLinksAPackage(
        array $pluginConfig,
        array $expectFileExists,
        array $expectFileNotExists
    ): void {
        // Configure and run the plugin using the provided config
        $this->configurePlugin($pluginConfig);
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
            $this->assertFileExists($this->toAbsolutePath($destinationDir));
            $this->assertSame($usesSymlink, is_link($this->toAbsolutePath($destinationDir)));

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
            $this->assertFileExists($this->toAbsolutePath($expectedFileStub));

            if ($checkFileLinks === true) {
                $this->assertSame($usesSymlink, is_link($this->toAbsolutePath($expectedFileStub)));
            }
        }

        // Ensure every expected file does not exist
        foreach ($expectFileNotExists as $notExpectedFileStub) {
            $this->assertFileDoesNotExist($this->toAbsolutePath($notExpectedFileStub));
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
     */
    public function testItUnlinksAPackage(
        array $pluginConfig,
        array $extraFiles,
        array $expectFileNotExists,
        array $expectFileExists
    ): void {
        // Configure and run the plugin creating the link file structure
        $this->configurePlugin($pluginConfig);
        $this->runPlugin('link');

        // Add extra files into link dir as needed
        // Useful when testing orphan cleanup
        if (!empty($extraFiles)) {
            $fileSystem = new Filesystem();

            foreach ($extraFiles as $extraFileStub) {
                $extraFilePath = $this->toAbsolutePath($extraFileStub);

                if ($fileSystem->exists(dirname($extraFilePath)) === false) {
                    $fileSystem->mkdir(dirname($extraFilePath));
                }

                $fileSystem->touch($extraFilePath);
            }
        }

        // Unlink the plugin
        $this->runPlugin('unlink');

        // Check all of the expected non existent files do not exist
        foreach ($expectFileNotExists as $expectFileNotExistStub) {
            $this->assertFileDoesNotExist($this->toAbsolutePath($expectFileNotExistStub));
        }

        // Check all of the expected existing files still exists.
        foreach ($expectFileExists as $expectedFileExistsStub) {
            $this->assertFileExists($this->toAbsolutePath($expectedFileExistsStub));
        }
    }


    public function testItCleansUpAfterPluginUninstall(): void
    {
        // Create mock package files
        $fileSystem = new Filesystem();
        $fileSystem->mkdir([
            $this->toAbsolutePath('vendor/package-one'),
            $this->toAbsolutePath('vendor/package-two')
        ]);
        $fileSystem->touch([
            $this->toAbsolutePath('vendor/package-one/file.txt'),
            $this->toAbsolutePath('vendor/package-two/file.txt')
        ]);

        // Create the mock packages
        $packageOne = $this->createMock(PackageInterface::class);
        $packageOne
            ->method('getName')
            ->willReturn('package/one');
        $packageTwo = $this->createMock(PackageInterface::class);
        $packageTwo
            ->method('getName')
            ->willReturn('package/two');

        // Configure composer's local repository to store these two packages
        // Essentially telling the mocks and the plugin that these packages
        // are installed
        $this->configureLocalRepository([
            $packageOne,
            $packageTwo
        ]);

        // Configure the installation manager to return expected package dirs
        // for the two mocked packages
        $this->installationManager
            ->method('getInstallPath')
            ->willReturnCallback(function ($package) use ($packageOne, $packageTwo) {
                if ($package === $packageOne) {
                    return $this->toAbsolutePath('vendor/package-one');
                }

                if ($package === $packageTwo) {
                    return $this->toAbsolutePath('vendor/package-two');
                }

                throw new InvalidArgumentException('Unhandled packaged');
            });

        // Configure the plugin
        $this->configurePlugin([
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
        $fileSystem->mkdir([
            $this->toAbsolutePath('linked-package-one'),
            $this->toAbsolutePath('linked-package-two')
        ]);
        $fileSystem->touch([
            $this->toAbsolutePath('linked-package-one/file.txt'),
            $this->toAbsolutePath('linked-package-two/file.txt')
        ]);

        // Assert everything exists as expected
        $this->assertFileExists($this->toAbsolutePath('vendor/package-one'));
        $this->assertFileExists($this->toAbsolutePath('vendor/package-one/file.txt'));
        $this->assertFileExists($this->toAbsolutePath('vendor/package-two'));
        $this->assertFileExists($this->toAbsolutePath('vendor/package-two/file.txt'));
        $this->assertFileExists($this->toAbsolutePath('linked-package-one'));
        $this->assertFileExists($this->toAbsolutePath('linked-package-one/file.txt'));
        $this->assertFileExists($this->toAbsolutePath('linked-package-two'));
        $this->assertFileExists($this->toAbsolutePath('linked-package-two/file.txt'));

        // Create a mock package for the plugin
        $pluginPackage = $this->createMock(PackageInterface::class);
        $pluginPackage
            ->method('getName')
            ->willReturn('jparkinson1991/composer-linker-plugin');

        // Run the cleanup action on the plugin
        $this->runPlugin('cleanup', $pluginPackage);

        // Ensure non of the linked files exist anymore
        $this->assertFileDoesNotExist($this->toAbsolutePath('linked-package-one'));
        $this->assertFileDoesNotExist($this->toAbsolutePath('linked-package-one/file.txt'));
        $this->assertFileDoesNotExist($this->toAbsolutePath('linked-package-two'));
        $this->assertFileDoesNotExist($this->toAbsolutePath('linked-package-two/file.txt'));
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
     * Configure the plugin via the 'extra' section of composer.json
     *
     * @param array $config
     *     The config to set for the 'extra' array
     *
     * @return void
     */
    protected function configurePlugin(array $config): void
    {
        $this->rootPackage
            ->method('getExtra')
            ->willReturn($config);
    }

    /**
     * Configures the mocked repository manager, to return a local repository
     * containing the given $packages
     *
     * @param PackageInterface[] $packages
     *     The packages to store in the local repository
     *
     * @return void
     */
    protected function configureLocalRepository(array $packages): void
    {
        $localRepository = $this->createMock(RepositoryInterface::class);
        $localRepository
            ->method('getPackages')
            ->willReturn($packages);

        $this->repositoryManager
            ->method('getLocalRepository')
            ->willReturn($localRepository);
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
     * @return void
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
            $this->composer,
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
            ->willReturn($this->composer);
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
            case 'cleanup':
                $composerLinkerPlugin->cleanUpPlugin($event);
                break;
            default:
                throw new RuntimeException('what chu talkin bout willis');
        }
    }

    /**
     * Returns an absolute path resolved from the test project root
     *
     * @param string $stub
     *     The path stub
     *
     * @return string
     *     The absolute path
     */
    protected function toAbsolutePath(string $stub): string
    {
        return $this->projectRootPath.'/'.ltrim($stub, '/');
    }
}
