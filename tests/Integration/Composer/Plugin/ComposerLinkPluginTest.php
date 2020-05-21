<?php
/**
 * @file
 * ComposerLinkPluginTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Plugin;

use Composer\Composer;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackage;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractor;
use JParkinson1991\ComposerLinkerPlugin\Composer\Plugin\ComposerLinkerPlugin;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;
use JParkinson1991\ComposerLinkerPlugin\Tests\Support\ReflectionMutatorTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ComposerLinkPluginTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer\Plugin
 */
class ComposerLinkPluginTest extends TestCase
{
    /**
     * Use reflection mutation capabilities
     */
    use ReflectionMutatorTrait;

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
     * Mocked test package object with name TEST_PACKAGE_NAME
     *
     * @var \Composer\Package\PackageInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $package;

    /**
     * Holds what will be treat as the project root path during these tests
     */
    protected $projectRootPath;

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
        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager
            ->method('getInstallPath')
            ->with($this->package)
            ->willReturn($this->projectRootPath.'/vendor/test/package');

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
            ->willReturn($installationManager);
        $this->composer
            ->method('getPackage')
            ->willReturn($this->rootPackage);

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
     * @param bool $expectSymlink
     *     Is it expected that files/dirs will be symlinked by the $pluginConfig
     * @param array $pluginConfig
     *     The plugin config to test
     * @param string[] $expectFileExists
     *     Expected files to exist
     * @param string[] $expectFileNotExists
     *     Expected files to not exists
     *
     * @return void
     *
     * @throws \ReflectionException
     */
    public function testItLinksADirectory(
        bool $expectSymlink,
        array $pluginConfig,
        array $expectFileExists,
        array $expectFileNotExists
    ): void {
        // Configure and run the plugin using the provided config
        $this->configurePlugin($pluginConfig);
        $this->runPlugin('link');

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
            $this->assertSame($expectSymlink, is_link($this->toAbsolutePath($destinationDir)));

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
                $this->assertSame($expectSymlink, is_link($this->toAbsolutePath($expectedFileStub)));
            }
        }

        // Ensure every expected file does not exist
        foreach ($expectFileNotExists as $notExpectedFileStub) {
            $this->assertFileDoesNotExist($this->toAbsolutePath($notExpectedFileStub));
        }
    }

    /**
     * Provides data for the plugin linking test
     *
     * @return array[]
     *     An array of paramter sets where each element in the set is:
     *         - bool: expect symlinking?
     *         - array: plugin configuration
     *         - array: expected files to exist
     *                  Use stubs from plugin destination dir
     *                  Absolute path resolved by test case
     *         - array: expected files to not exists
     *                  Use stubs from plugin destination dir
     *                  Absolute path resolved by test case
     */
    public function dataProviderLinking(): array
    {
        return [
            'linked dir - symlinked' => [
                true,
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
                []
            ],
            'linked dir - copied - global option' => [
                false,
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
                []
            ],
            'linked dir - symlink - package option override' => [
                true,
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
                []
            ],
            'linked dir - copied - package option override' => [
                false,
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
                []
            ],
            'linked files - symlink' => [
                true,
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
                false,
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
     * Runs the given method on the plugin
     *
     *
     *
     * @param string $action
     *     Either link, or unlink
     *
     * @return void
     *
     * @throws \ReflectionException
     */
    protected function runPlugin(string $action): void
    {
        // Create the plugin, using class property composer configured and
        // ready to use test project package etc
        $composerLinkerPlugin = new ComposerLinkerPlugin();
        $composerLinkerPlugin->activate(
            $this->composer,
            $this->createMock(IOInterface::class)
        );

        $event = $this->createMock(PackageEvent::class);

        // Create a package extractor mock returning the test package with
        // the test event
        $packageExtractor = $this->createMock(PackageExtractor::class);
        $packageExtractor
            ->method('extractFromEvent')
            ->with($event)
            ->willReturn($this->package);

        // Inject the package extractor mock
        $this->setPropertyValue(
            $composerLinkerPlugin,
            'packageExtractor',
            $packageExtractor
        );

        switch ($action) {
            case 'link':
                $composerLinkerPlugin->linkPackageFromEvent($event);
                break;
            case 'unlink':
                $composerLinkerPlugin->unlinkPackageFromEvent($event);
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
