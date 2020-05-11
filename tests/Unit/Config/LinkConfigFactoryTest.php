<?php
/**
 * @file
 * PackageLinkConfigFactoryTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Config;

use JParkinson1991\ComposerLinkerPlugin\Config\LinkConfig;
use JParkinson1991\ComposerLinkerPlugin\Config\LinkConfigFactory;
use JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException;
use PHPUnit\Framework\TestCase;

/**
 * Class LinkConfigFactoryTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Config
 */
class LinkConfigFactoryTest extends TestCase
{
    /**
     * Tests that dynamic definitions are routed to the correct creation methods
     */
    public function testItCorrectlyRoutesDynamicDefinitions()
    {
        $factory = $this->getMockBuilder(LinkConfigFactory::class)
            ->onlyMethods(['createFromString'])
            ->getMock();

        $factory->expects($this->once())
            ->method('createFromString');

        $factory->create('test/package', 'string/definition');
    }

    /**
     * Tests that unhandled dynamic definition types trigger invalid config
     * exception when passed to the flexible 'create' method on the factory
     */
    public function testItThrowsExceptionOnUnhandledDynamicDefinition()
    {
        $factory = new LinkConfigFactory();

        $this->expectException(InvalidConfigException::class);
        $factory->create('test/package', new \stdClass());
    }

    /**
     * Tests that the factory creates the config instance as expected when
     * explicitly created from a package name and destination directory/
     */
    public function testItCreatesFromAString()
    {
        $factory = new LinkConfigFactory();
        $config = $factory->createFromString('package/name', 'destination/dir');

        $this->assertInstanceOf(LinkConfig::class, $config);
        $this->assertSame('destination/dir', $config->getDestinationDir());
    }
}
