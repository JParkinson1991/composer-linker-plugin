<?php
/**
 * @file
 * LinkDefinitionTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Link;

use Composer\Package\PackageInterface;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Class LinkDefinitionTest
 *
 * @package JParkinson1991\Tests\Unit\Link
 */
class LinkDefinitionTest extends TestCase
{
    /**
     * Tests link definitions are instantiated as expected
     */
    public function testInstantaiton()
    {
        $package = $this->createMock(PackageInterface::class);

        $linkDefinition = new LinkDefinition($package, '/destination/dir');

        $this->assertSame($package, $linkDefinition->getPackage());
        $this->assertSame('/destination/dir', $linkDefinition->getDestinationDir());
    }

    /**
     * Tests that in it's default state link definitions to not copy files
     */
    public function testItDoesNotCopyFilesByDefault()
    {
        $linkDefinition = new LinkDefinition(
            $this->createMock(PackageInterface::class),
            '/destination/dir'
        );

        $this->assertFalse($linkDefinition->getCopyFiles());
    }

    /**
     * Tests that copying of linked files can be enabled after instantiation
     * of a link definition
     */
    public function testCopyFilesCanBeEnabledAfterInstantiation()
    {
        $linkDefinition = new LinkDefinition(
            $this->createMock(PackageInterface::class),
            '/destination/dir'
        );

        $linkDefinition->setCopyFiles(true);

        $this->assertTrue($linkDefinition->getCopyFiles());
    }
}
