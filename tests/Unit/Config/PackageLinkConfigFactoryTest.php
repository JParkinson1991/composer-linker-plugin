<?php
/**
 * @file
 * PackageLinkConfigFactoryTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Config;

use JParkinson1991\ComposerLinkerPlugin\Config\PackageLinkConfig;
use JParkinson1991\ComposerLinkerPlugin\Exception\PackageLinkConfigInvalidArrayDefinitionException;
use JParkinson1991\ComposerLinkerPlugin\Factory\PackageLinkConfigFactory;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Class PackageLinkConfigFactoryTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Config
 */
class PackageLinkConfigFactoryTest extends TestCase
{
    /**
     * The factory being tested
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Factory\PackageLinkConfigFactory
     */
    protected $factory;

    /**
     * Sets up this class prior to running each test case
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->factory = new PackageLinkConfigFactory();
    }

    /**
     * Tests that package link config classes are created correctly from
     * array definitions
     */
    public function testItCreatesPackageLinkConfigFromArray(): void
    {
        $package = $this->factory->createFromArray('test/package', [
            'dir' => 'new/directory'
        ]);

        $this->assertInstanceOf(PackageLinkConfig::class, $package);
    }

    /**
     * Tests that an exception is throw when trying to create a package
     * config instance from an array that is missing the required directory key
     * or that key is not of the requested type
     *
     * @dataProvider invalidArrayDefinitionProvider
     *
     * @param array $definitionArray
     *     The invalid definition array
     * @param int $expectedExceptionCode
     *     The expected exception code to be triggered from the use of the
     *     invalid definition array
     * @param string|null $expectedExceptionMessage
     *     An expected exception messagep
     */
    public function testItThrowsExceptionWhenArrayDefinitionHasInvalidDir(
        array $definitionArray,
        int $expectedExceptionCode,
        string $expectedExceptionMessage = null
    ): void {
        $this->expectException(PackageLinkConfigInvalidArrayDefinitionException::class);
        $this->expectExceptionCode($expectedExceptionCode);
        if ($expectedExceptionMessage !== null) {
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $this->factory->createFromArray('test/package', $definitionArray);
    }

    /**
     * Provides invalid definition arrays paired with their expected
     * exception code.
     *
     * @return array|array[]
     */
    public function invalidArrayDefinitionProvider(): array
    {
        return [
            [
                [],
                PackageLinkConfigInvalidArrayDefinitionException::CODE_MISSING_KEY_DATA,
            ],
            [
                ['dir' => false],
                PackageLinkConfigInvalidArrayDefinitionException::CODE_INVALID_DATA_TYPE,
            ],
            [
                ['dir' => null],
                PackageLinkConfigInvalidArrayDefinitionException::CODE_INVALID_DATA_TYPE,
                'Invalid value data type for dir. Expected string. Got NULL'
            ],
            [
                ['dir' => new stdClass()],
                PackageLinkConfigInvalidArrayDefinitionException::CODE_INVALID_DATA_TYPE
            ]
        ];
    }
}
