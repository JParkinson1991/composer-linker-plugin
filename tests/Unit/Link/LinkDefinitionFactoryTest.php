<?php
/**
 * @file
 * LinkDefinitionFactoryTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Link;

use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException;
use JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;
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
     * Tests that an exception is thrown by the factory if it cannot find
     * any matching plugin config for the given package
     *
     * @dataProvider dataProviderForItThrowsExceptionWhenConfigNotFound
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     */
    public function testItThrowsExceptionWhenConfigNotFound(array $config): void
    {
        $factory = $this->instantiateFactoryWithConfigArray($config);

        $this->expectException(ConfigNotFoundException::class);

        $factory->createForPackage($this->instantiateMockPackage('test/package'));
    }

    /**
     * Tests that it throws exception for invalid link config type
     *
     * @dataProvider dataProviderForItThrowsExceptionForInvalidLinkConfigType
     *
     * @param array $config
     *     The link config
     * @param string $packageName
     *     The name of the package
     *
     */
    public function testItThrowsExceptionForInvalidLinkConfigType($config): void
    {
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => $config
                ]
            ]
        ]);

        $this->expectException(InvalidConfigException::class);

        $factory->createForPackage($this->instantiateMockPackage('test/package'));
    }

    /**
     * Tests that the factory can create a link definition instance for a
     * package that has simple string configuration
     */
    public function testItCreatesInstanceFromSimpleStringConfig(): void
    {
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => 'destination/dir'
                ]
            ]
        ]);

        $package = $this->instantiateMockPackage('test/package');
        $instance = $factory->createForPackage($package);

        $this->assertSame($package, $instance->getPackage());
        $this->assertSame('destination/dir', $instance->getDestinationDir());
    }

    /**
     * Tests that the factory will throw invalid config exceptions if the
     * structure of the global options array is invalid (ie, not an array)
     *
     * @dataProvider dataProviderForItThrowsExceptionOnInvalidGlobalConfigOptions
     */
    public function testItThrowsExceptionOnInvalidGlobalConfigOptionsStructure($optionsConfig): void
    {
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => 'destination/dir'
                ],
                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => $optionsConfig
            ]
        ]);

        $this->expectException(InvalidConfigException::class);
        $factory->createForPackage($this->instantiateMockPackage('test/package'));
    }

    /**
     * Tests that the factory will throw an invalid config exception if the
     * copy option received is not a boolean
     *
     * @dataProvider dataProviderForItThrowsExceptionOnInvalidCopyConfigOptionValue
     */
    public function testItThrowsExceptionOnInvalidCopyConfigOptionValue($copyValue): void
    {
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => 'destination/dir'
                ],
                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => $copyValue
                ]
            ]
        ]);

        $this->expectException(InvalidConfigException::class);
        $factory->createForPackage($this->instantiateMockPackage('test/package'));
    }

    /**
     * Tests that global defined config options are applied to link definition
     * instances created by the factory
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public function testThatGlobalConfigOptionsAreAppliedToSimpleLinkDefinitionInstances(): void
    {
        $testPackage = $this->instantiateMockPackage('test/package');
        $anotherPackage = $this->instantiateMockPackage('another/package');

        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => 'destination/dir',
                    'another/package' => 'another/dir'
                ],
                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true
                ]
            ]
        ]);

        // Instantiate a test package definition check outputs
        $testPackageLinkDefinition = $factory->createForPackage($testPackage);
        $this->assertSame($testPackage, $testPackageLinkDefinition->getPackage());
        $this->assertSame('destination/dir', $testPackageLinkDefinition->getDestinationDir());
        $this->assertTrue($testPackageLinkDefinition->getCopyFiles());

        // Instantiate the other test package definition check outputs
        $anotherPackageLinkDefinition = $factory->createForPackage($anotherPackage);
        $this->assertSame($anotherPackage, $anotherPackageLinkDefinition->getPackage());
        $this->assertSame('another/dir', $anotherPackageLinkDefinition->getDestinationDir());
        $this->assertTrue($anotherPackageLinkDefinition->getCopyFiles());

        // Reinstantiate the factory changing copy option value, make sure
        // previous assertations were not flukes
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => 'destination/dir',
                    'another/package' => 'another/dir'
                ],
                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => false
                ]
            ]
        ]);

        // Recreate test package link definition check copy false
        $testPackageLinkDefinition = $factory->createForPackage($testPackage);
        $this->assertFalse($testPackageLinkDefinition->getCopyFiles());

        // Do the same for another package
        $anotherPackageLinkDefinition = $factory->createForPackage($anotherPackage);
        $this->assertFalse($anotherPackageLinkDefinition->getCopyFiles());
    }

    /**
     * Tests that when a complex package config is used in the plugin config
     * ie, the extra data that it must contain the destination dir key. If
     * that key is not present then the factory should throw an invalid config
     * exception.
     */
    public function testItThrowsInvalidConfigExceptionIfComplexConfigDoesntContainDestinationDir(): void
    {
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [] // no dir
                ]
            ]
        ]);

        $this->expectException(InvalidConfigException::class);
        $factory->createForPackage($this->instantiateMockPackage('test/package'));
    }

    /**
     * Tests that an exception is thrown when a complex package config is used
     * but the destination dir given is not the required type (string).
     *
     * @dataProvider dataProviderForItThrowsInvalidConfigExceptionIfComplexConfigDestinationDirHasInvalidType
     */
    public function testItThrowsInvalidConfigExceptionIfComplexConfigDestinationDirHasInvalidType($destinationDir): void
    {
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => $destinationDir
                    ]
                ]
            ]
        ]);

        $this->expectException(InvalidConfigException::class);
        $factory->createForPackage($this->instantiateMockPackage('test/package'));
    }

    /**
     * Tests that the factory can create a valid link definition instance
     * from complex config
     */
    public function testItCreatesALinkDefinitionInstanceFromComplexConfig(): void
    {
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'destination/dir'
                    ]
                ]
            ]
        ]);

        $package = $this->instantiateMockPackage('test/package');
        $instance = $factory->createForPackage($package);

        $this->assertSame($package, $instance->getPackage());
        $this->assertSame('destination/dir', $instance->getDestinationDir());
    }

    /**
     * Tests that global options are applied to complex link definitions
     *
     * @return void
     */
    public function testItUsesGlobalOptionsWhenInstantiatingFromComplexConfig(): void
    {
        $package = $this->instantiateMockPackage('test/package');

        // Create the factory with given extra config
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'destination/dir'
                    ]
                ],
                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true
                ]
            ]
        ]);

        $instance = $factory->createForPackage($package);
        $this->assertSame($package, $instance->getPackage());
        $this->assertSame('destination/dir', $instance->getDestinationDir());
        $this->assertTrue($instance->getCopyFiles());

        // Recreate factory with global copy option set to false
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'destination/dir'
                    ]
                ],
                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => false
                ]
            ]
        ]);

        // Recreate instance, check copy now false
        $instance = $factory->createForPackage($package);
        $this->assertFalse($instance->getCopyFiles());
    }

    /**
     * Test that if a complex configuration provides package level options
     * that they are validate as expected and throw exceptions if they are
     * invalid
     *
     * @return void
     */
    public function testThatInvalidOptionInComplexConfigTriggerExceptions(): void
    {
        // Create factory with complex config defining package level options
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'destination/dir',
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => 'not a boolean'
                        ]
                    ]
                ]
            ]
        ]);

        $this->expectException(InvalidConfigException::class);
        $factory->createForPackage($this->instantiateMockPackage('test/package'));
    }

    /**
     * Tests that any options set at package level are actually applied to
     * the instance created (assuming they are valid)
     *
     * @return void
     */
    public function testThatPackageLevelOptionsAreSetAgainstCreatedInstance(): void
    {
        $package = $this->instantiateMockPackage('test/package');

        // Create factory with complex config defining package level options
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'destination/dir',
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true
                        ]
                    ]
                ]
            ]
        ]);

        $instance = $factory->createForPackage($package);
        $this->assertSame($package, $instance->getPackage());
        $this->assertSame('destination/dir', $instance->getDestinationDir());
        $this->assertTrue($instance->getCopyFiles());

        // Recreate the factory with new copy value
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'destination/dir',
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => false
                        ]
                    ]
                ]
            ]
        ]);

        // Recreate instance assert it does not use copy
        $instance = $factory->createForPackage($package);
        $this->assertFalse($instance->getCopyFiles());
    }

    /**
     * Test that package level options take precedence over global options when
     * instances are created by the factory.
     *
     * @return void
     */
    public function testThatGlobalOptionsAreOverriddenByPackageOptions(): void
    {
        $package = $this->instantiateMockPackage('test/package');

        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'destination/dir',
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true
                        ]
                    ]
                ],
                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => false
                ]
            ]
        ]);

        $instance = $factory->createForPackage($package);
        $this->assertTrue($instance->getCopyFiles());

        // Recreate factory change config values around
        $factory = $this->instantiateFactoryWithConfigArray([
            LinkDefinitionFactory::CONFIG_KEY_ROOT => [
                LinkDefinitionFactory::CONFIG_KEY_LINKS => [
                    'test/package' => [
                        LinkDefinitionFactory::CONFIG_KEY_LINKS_DIR => 'destination/dir',
                        LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                            LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => false
                        ]
                    ]
                ],
                LinkDefinitionFactory::CONFIG_KEY_OPTIONS => [
                    LinkDefinitionFactory::CONFIG_KEY_OPTIONS_COPY => true
                ]
            ]
        ]);

        // Ensure new instance does not use copy
        $instance = $factory->createForPackage($package);
        $this->assertFalse($instance->getCopyFiles());
    }

    /**
     * Provides data for the testItThrowsExceptionWhenConfigNotFound
     *
     * Each record, contains the array that will become the data returned form
     * the 'getExtra' call used by the factory when reading data from the root
     * package.
     *
     * Every parameter set from this method should trigger the config not
     * found exception
     *
     * @see testItThrowsExceptionWhenConfigNotFound
     *
     * @return array[]
     */
    public function dataProviderForItThrowsExceptionWhenConfigNotFound()
    {
        return [
            'empty-config' => [
                []
            ],
            'invalid-config-no-links' => [
                [
                    'linker-plugin' => []
                ]
            ],
            'valid-config-no-package-config' => [
                [
                    'linker-plugin' => [
                        'links' => []
                    ]
                ]
            ],
            'valid-config-no-matching-package-config' => [
                [
                    'linker-plugin' => [
                        'links' => [
                            'not-the-test/package' => [] // this name is different than the one used in test case
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Data provider for the ItThrowsExceptionForInvalidLinkConfigType testcase
     *
     * Returns invalid configs that could be found at the package level in
     * the plugin. Ie. under 'linker-plugin']['links']['package name
     *
     * Ensure all unexpected types throw exception
     *
     * @see testItThrowsExceptionForInvalidLinkConfigType
     *
     * @return array
     */
    public function dataProviderForItThrowsExceptionForInvalidLinkConfigType(): array
    {
        return [
            [ true ],
            [ false ],
            [ 1 ],
            [ 1.23 ],
            [ new stdClass() ]
        ];
    }

    /**
     * Data provider for testItThrowsExceptionOnInvalidGlobalConfigOptions
     *
     * Each item returned represents an invalid global config definition, the
     * associated test case wraps it as required, each returned parameter set
     * should trigger an exception
     *
     * @see testItThrowsExceptionOnInvalidGlobalConfigOptions
     *
     * @return array
     */
    public function dataProviderForItThrowsExceptionOnInvalidGlobalConfigOptions(): array
    {
        return [
            [ "string" ],
            [ true ],
            [ false ],
            [ 1 ],
            [ 1.23 ],
            [ new stdClass() ]
        ];
    }

    /**
     * Data provider for testItThrowsExceptionOnInvalidCopyConfigOptionValue
     *
     * Each item returned represent an invalid value for the copy option, each
     * value should trigger an exception
     *
     * @return array
     */
    public function dataProviderForItThrowsExceptionOnInvalidCopyConfigOptionValue(): array
    {
        return [
            [ "string" ],
            [ [] ],
            [ 1 ],
            [ 1.23 ],
            [ new stdClass() ]
        ];
    }

    /**
     * Data provider for testItThrowsInvalidConfigExceptionIfComplexConfigDestinationDirHasInvalidType
     *
     * All returned parameter sets here represent invalid destination dir values
     * alla should trigger exceptions
     *
     * @see testItThrowsInvalidConfigExceptionIfComplexConfigDestinationDirHasInvalidType
     *
     * @return array
     */
    public function dataProviderForItThrowsInvalidConfigExceptionIfComplexConfigDestinationDirHasInvalidType(): array
    {
        return [
            [ true ],
            [ false ],
            [ [] ],
            [ 1 ],
            [ 1.23 ],
            [ new stdClass() ]
        ];
    }

    /**
     * Returns a LinkDefinitionFactoryInstance that reads plugin config
     * from the given array
     *
     * @param array $configArray
     *     The config array
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory
     */
    protected function instantiateFactoryWithConfigArray(array $configArray): LinkDefinitionFactory
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')
            ->willReturn($configArray);

        return new LinkDefinitionFactory($rootPackage);
    }

    /**
     * Returns a mock package with a given a name
     *
     * @param string $name
     *     The name of the package
     *
     * @return \Composer\Package\PackageInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function instantiateMockPackage(string $name): PackageInterface
    {
        $package = $this->createMock(PackageInterface::class);
        $package->method('getName')
            ->willReturn($name);

        return $package;
    }
}
