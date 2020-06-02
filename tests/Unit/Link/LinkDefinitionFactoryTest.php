<?php
/**
 * @file
 * LinkDefinitionFactoryTest.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Link;

use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException;
use JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;
use JParkinson1991\ComposerLinkerPlugin\Tests\Support\ArrayTestTrait;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Class LinkDefinitionFactoryTest
 *
 * @package JParkinson1991\Tests\Unit\Link
 */
class LinkDefinitionFactoryTest extends TestCase
{
    /**
     * Extend array assertations
     */
    use ArrayTestTrait;

    /**
     * Tests scenarios where the config from the composer.json 'extra'
     * definition should throw config not found for a package with the
     * given package name
     *
     * @dataProvider dataProviderConfigNotFoundExceptions
     *
     * @param array $config
     *     The config to make available to the factory instance
     * @param string $packageName
     *     The name of the package to try instantiate a link definition instance for
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public function testForConfigNotFoundException(array $config, string $packageName = 'test/package'): void
    {
        $factory = $this->createFactoryWithConfig($config);

        $this->expectException(ConfigNotFoundException::class);

        $factory->createForPackage($this->createMockPackage($packageName));
    }

    /**
     * Tests scenarios where the config from the composer.json 'extra'
     * definition should throw an invalid config exception when instantiating
     * a link definition for a package with the given name
     *
     * @dataProvider dataProviderForInvalidConfigExceptions
     *
     * @param array $config
     *     The config to make available to the factory instance
     * @param string $packageName
     *     The name of the package to try instantiate a link definition instance for
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public function testForInvalidConfigException(array $config, string $packageName = 'test/package'): void
    {
        $factory = $this->createFactoryWithConfig($config);

        $this->expectException(InvalidConfigException::class);

        $factory->createForPackage($this->createMockPackage($packageName));
    }

    /**
     * Tests that the factory is able to instantiate a link definition object
     * from a simple string package config
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public function testItCreatesCorrectlyFromString(): void
    {
        $factory = $this->createFactoryWithConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => 'dest/dir'
                ]
            ]
        ]);

        $package = $this->createMockPackage('test/package');
        $instance = $factory->createForPackage($package);

        $this->assertSame($package, $instance->getPackage());
        $this->assertSame('dest/dir', $instance->getDestinationDir());
    }

    /**
     * Tests that the factory is able to instantiate a link definition object
     * from a simple string package config
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public function testItCreatesCorrectlyFromStringWithOptions(): void
    {
        $factory = $this->createFactoryWithConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => 'dest/dir'
                ],
                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => false,
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => true
                ]
            ]
        ]);

        $package = $this->createMockPackage('test/package');
        $instance = $factory->createForPackage($package);

        $this->assertSame($package, $instance->getPackage());
        $this->assertSame('dest/dir', $instance->getDestinationDir());
        $this->assertFalse($instance->getCopyFiles());
        $this->assertTrue($instance->getDeleteOrphanDirs());

        // Create new values and swap options to ensure actually using values
        // from config and not getting false positives from definition property
        // defaults etc
        $factory = $this->createFactoryWithConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => 'dest/dir'
                ],
                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true,
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => false
                ]
            ]
        ]);

        $package = $this->createMockPackage('test/package');
        $instance = $factory->createForPackage($package);

        $this->assertSame($package, $instance->getPackage());
        $this->assertSame('dest/dir', $instance->getDestinationDir());
        $this->assertTrue($instance->getCopyFiles());
        $this->assertFalse($instance->getDeleteOrphanDirs());
    }

    /**
     * Tests that the factory is able to instantiate a link definition from
     * a simple (minimum required elements) array
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public function testItCreatesCorrectlyFromArray(): void
    {
        $factory = $this->createFactoryWithConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => '/destination/dir'
                    ]
                ]
            ]
        ]);

        $package = $this->createMockPackage('test/package');
        $instance = $factory->createForPackage($package);

        $this->assertSame($package, $instance->getPackage());
        $this->assertSame('/destination/dir', $instance->getDestinationDir());
    }

    /**
     * Tests that the factory is able to instantiate a link definition from an
     * array config and inherit any defined global options
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public function testItCreatesCorrectlyFromArrayWithGlobalOptions(): void
    {
        $factory = $this->createFactoryWithConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => '/destination/dir'
                    ]
                ],
                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true,
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => false
                ]
            ]
        ]);

        $package = $this->createMockPackage('test/package');
        $instance = $factory->createForPackage($package);

        $this->assertSame($package, $instance->getPackage());
        $this->assertSame('/destination/dir', $instance->getDestinationDir());
        $this->assertTrue($instance->getCopyFiles());
        $this->assertFalse($instance->getDeleteOrphanDirs());
    }

    /**
     * Tests that the factory is able to instantiate a link definition from an
     * array config that overrides global options at package level.
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public function testItCreatesCorrectlyFromArrayWithOptionOverrides(): void
    {
        $factory = $this->createFactoryWithConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => '/destination/dir',
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => false,
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => true
                        ]
                    ]
                ],
                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true,
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => false
                ]
            ]
        ]);

        $package = $this->createMockPackage('test/package');
        $instance = $factory->createForPackage($package);

        $this->assertSame($package, $instance->getPackage());
        $this->assertSame('/destination/dir', $instance->getDestinationDir());
        $this->assertFalse($instance->getCopyFiles());
        $this->assertTrue($instance->getDeleteOrphanDirs());
    }

    /**
     * Tests that the factory is able to instantiate a link definition from an
     * array config that defines specific file mappings.
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public function testItCreatesCorrectlyFromArrayWithFileMappings(): void
    {
        $factory = $this->createFactoryWithConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => '/destination/dir',
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                            'same-source-dest.txt',
                            [
                                'nested-array-source-dest-pair-source.txt' => 'nested-array-source-dest-pair-dest.txt',
                                'nested-array-source-multiple-dest-source.txt' => [
                                    'nested-array-source-multiple-dest-dest-1.txt',
                                    'nested-array-source-multiple-dest-dest-2.txt',
                                    'nested-array-source-multiple-dest-dest-3.txt'
                                ]
                            ],
                            //phpcs:ignore
                            'source-dest-pair-source.txt' => 'source-dest-pair-dest.txt',
                            'source-multiple-dest-source.txt' => [
                                'source-multiple-dest-dest-1.txt',
                                'source-multiple-dest-dest-2.txt'
                            ],
                            // try use same source again with difference dest
                            'same-source-dest.txt' => 'add-additional-with-specific-dest.txt'
                        ]
                    ]
                ]
            ]
        ]);

        // Expected file mappings output dir as formatted by the link definition
        $expectedFileMappings = [
            'same-source-dest.txt' => [
                'same-source-dest.txt',
                'add-additional-with-specific-dest.txt'
            ],
            'nested-array-source-dest-pair-source.txt' => [
                'nested-array-source-dest-pair-dest.txt'
            ],
            'nested-array-source-multiple-dest-source.txt' => [
                'nested-array-source-multiple-dest-dest-1.txt',
                'nested-array-source-multiple-dest-dest-2.txt',
                'nested-array-source-multiple-dest-dest-3.txt'
            ],
            'source-dest-pair-source.txt' => [
                'source-dest-pair-dest.txt'
            ],
            'source-multiple-dest-source.txt' => [
                'source-multiple-dest-dest-1.txt',
                'source-multiple-dest-dest-2.txt'
            ]
        ];

        $package = $this->createMockPackage('test/package');
        $instance = $factory->createForPackage($package);

        $this->assertSame($package, $instance->getPackage());
        $this->assertSame('/destination/dir', $instance->getDestinationDir());
        $this->assertArraySame($expectedFileMappings, $instance->getFileMappings());
    }

    /**
     * Tests that any exceptions thrown during application of file mappings
     * where not already an invalid config exception are intercepted and
     * wrapped by the factory and thrown as an invalid config exception
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public function testThatFileMappingExceptionsAreWrappedAsRequired(): void
    {
        // Use the same destination which should trigger an exception on the
        // link definition, this exception should be caught and wrapped by the
        // factory
        $factory = $this->createFactoryWithConfig([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => '/destination/dir',
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                            'source.txt' => 'dest.txt',
                            'source-1.txt' => 'dest.txt'
                        ]
                    ]
                ]
            ]
        ]);

        $this->expectException(InvalidConfigException::class);
        $factory->createForPackage($this->createMockPackage('test/package'));
    }

    /**
     * Data provider return configuration arrays and optional package names
     * that should, when parsed by the factory to get a link definition for
     * a package with that name, throw a config not found exception
     *
     * @see testForConfigNotFoundException
     *
     * @return array
     *     Array of parameter sets, where:
     *         key => parameter set label
     *         value => array containing
     *                  config array as element 0
     *                  optional package name as element 1
     */
    public function dataProviderConfigNotFoundExceptions(): array
    {
        return [
            'no plugin element' => [
                [] // empty config array
            ],
            'plugin element, no link element' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => []
                ]
            ],
            'plugin element, link element, no package definition' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => []
                    ]
                ]
            ]
        ];
    }

    /**
     * Data provider returning configuration arrays and optional package names
     * that should, when parsed by the factory to instantiate a link definition
     * for a package with that name, throw an invalid config exception
     *
     * @see testForInvalidConfigException
     *
     * @return array
     *     Array of parameter sets, where:
     *         key => parameter set label
     *         value => array containing
     *                  config array as element 0
     *                  optional package name as element 1
     */
    public function dataProviderForInvalidConfigExceptions(): array
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        // phpcs:disable Squiz.Arrays.ArrayDeclaration.MultiLineNotAllowed

        return [
            'Package Config: Defined as bool' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => true
                        ]
                    ]
                ]
            ],
            'Package Config: Defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => 2
                        ]
                    ]
                ]
            ],
            'Package Config: Defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => 1.23
                        ]
                    ]
                ]
            ],
            'Package Config: Defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => new stdClass()
                        ]
                    ]
                ]
            ],
            'Package Config: Dir element not defined' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                'no-dir-key' => true
                            ]
                        ]
                    ]
                ]
            ],
            'Package Config: Dir element defined as bool' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => true
                            ]
                        ]
                    ]
                ]
            ],
            'Package Config: Dir element defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 1
                            ]
                        ]
                    ]
                ]
            ],
            'Package Config: Dir element defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 1.23
                            ]
                        ]
                    ]
                ]
            ],
            'Package Config: Dir element defined as array' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => []
                            ]
                        ]
                    ]
                ]
            ],
            'Package Config: Dir element defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => new stdClass()
                            ]
                        ]
                    ]
                ]
            ],
            'Global Options: Defined as bool' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => true
                    ]
                ]
            ],
            'Global Options: Defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => 1
                    ]
                ]
            ],
            'Global Options: Defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => 1.23
                    ]
                ]
            ],
            'Global Options: Defined as string' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => 'string'
                    ]
                ]
            ],
            'Global Options: Defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => new stdClass()
                    ]
                ]
            ],
            'Global Options - Copy Option: Defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => 1
                        ]
                    ]
                ]
            ],
            'Global Options - Copy Option: Defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => 1.23
                        ]
                    ]
                ]
            ],
            'Global Options - Copy Option: Defined as string' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => 'string'
                        ]
                    ]
                ]
            ],
            'Global Options - Copy Option: Defined as array' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => []
                        ]
                    ]
                ]
            ],
            'Global Options - Copy Option: Defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => 'object'
                        ]
                    ]
                ]
            ],
            'Global Options - Delete Orphans Option: Defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => 1
                        ]
                    ]
                ]
            ],
            'Global Options - Delete Orphans Option: Defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => 1.23
                        ]
                    ]
                ]
            ],
            'Global Options - Delete Orphans Option: Defined as string' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => "string"
                        ]
                    ]
                ]
            ],
            'Global Options - Delete Orphans Option: Defined as array' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => []
                        ]
                    ]
                ]
            ],
            'Global Options - Delete Orphans Option: Defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir'
                            ]
                        ],
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => new stdClass()
                        ]
                    ]
                ]
            ],
            'Package Option Overrides: Defined as bool' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => true
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides: Defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => 1
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides: Defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => 1.23
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides: Defined as string' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => 'options as string'
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides: Defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => new stdClass()

                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides - Copy Option: Defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => 1
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides - Copy Option: Defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => 1.23
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides - Copy Option: Defined as string' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => 'string'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides - Copy Option: Defined as array' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => []
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides - Copy Option: Defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => new stdClass()
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides - Delete Orphans Option: Defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => 1
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides - Delete Orphans Option: Defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => 1.23
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides - Delete Orphans Option: Defined as string' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => 'string'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides - Delete Orphans Option: Defined as array' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => []
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package Option Overrides - Delete Orphans Option: Defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_DELETEORPHANS => new stdClass()
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings: Defined as bool' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => true
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings: Defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => 1
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings: Defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => 1.23
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings: Defined as string' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => 'string'
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings: Defined as empty array' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => []
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings: Defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => new stdClass()
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat source defined as bool' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    true
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat source defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    1
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat source defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    1.23
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat source defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    new stdClass()
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Source => Destination, destination defined as bool' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'source.txt' => true
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Source => Destination, destination defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'source.txt' => 1
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Source => Destination, destination defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'source.txt' => 1.23
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Source => Destination, destination defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'source.txt' => new stdClass()
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Source => Multiple destinations but none defined' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'source.txt' => []
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Source => Multiple destinations, destination defined as bool' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'source-multiple-destinations.txt' => [
                                        true
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Source => Multiple destinations, destination defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'source-multiple-destinations.txt' => [
                                        1
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Source => Multiple destinations, destination defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'source-multiple-destinations.txt' => [
                                        1.23
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Source => Multiple destinations, destination defined as array' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'source-multiple-destinations.txt' => [
                                        []
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Source => Multiple destinations, destination defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'source-multiple-destinations.txt' => [
                                        new stdClass()
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Source => Multiple destinations, destination defined as nested source => destination' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    'source-with-nested-source-dest.txt' => [
                                        'nested-source.txt' => 'dest.txt'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array empty' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [] // empty nested flat array
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array no source file key' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [
                                        'no key thus no sourcefile.txt'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array dest defined as bool' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [
                                        'nested-flat-array-source.txt' => true
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array dest defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [
                                        'nested-flat-array-source.txt' => 1
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array dest defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [
                                        'nested-flat-array-source.txt' => 1.23
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array dest defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [
                                        'nested-flat-array-source.txt' => new stdClass()
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array dest defined as empty array' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [
                                        'nested-flat-array-source.txt' => []
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array multiple destinations, destination defined as bool' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [
                                        'nested-flat-array-source.txt' => [
                                            true
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array multiple destinations, destination defined as int' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [
                                        'nested-flat-array-source.txt' => [
                                            1
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array multiple destinations, destination defined as float' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [
                                        'nested-flat-array-source.txt' => [
                                            1.23
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array multiple destinations, destination defined as array' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [
                                        'nested-flat-array-source.txt' => [
                                            []
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array multiple destinations, destination defined as object' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [
                                        'nested-flat-array-source.txt' => [
                                            new stdClass()
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'Package File Mappings - Invalid Array: Flat nested array multiple destinations, destination defined as source => dest' => [
                [
                    LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                            'test/package' => [
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'valid dir',
                                LinkDefinitionFactory::CONFIG_KEY_LINKS_FILES => [
                                    [
                                        'nested-flat-array-source.txt' => [
                                            'another-nested-source.txt' => 'dest'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // phpcs:enable Generic.Files.LineLength.TooLong
        // phpcs:enable Squiz.Arrays.ArrayDeclaration.MultiLineNotAllowed
    }

    /**
     * Instantiates a factory that reads config from the passed array
     *
     * @param array $config
     *     The array of config available for the factory to read
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory
     */
    protected function createFactoryWithConfig(array $config): LinkDefinitionFactory
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')
            ->willReturn($config);

        return new LinkDefinitionFactory($rootPackage);
    }

    /**
     * Returns a mock package with the given name
     *
     * @param string $name
     *
     * @return \Composer\Package\Package
     */
    protected function createMockPackage(string $name): PackageInterface
    {
        $package = $this->createMock(PackageInterface::class);
        $package->method('getName')
            ->willReturn($name);

        return $package;
    }
}
