<?php
/**
 * @file
 * LinkConfigTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Config;

use Composer\Package\Package;
use JParkinson1991\ComposerLinkerPlugin\Config\LinkConfig;
use PHPUnit\Framework\TestCase;

/**
 * Class LinkConfigTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Config
 */
class LinkConfigTest extends TestCase
{

    /**
     * Tests that the constructor parameters passed to the link config class
     * instantiate the object as expected
     */
    public function testInstantiatedCorrectly()
    {
        $linkConfig = new LinkConfig('test/package', 'destination/dir');

        // Check destination dir
        $this->assertSame('destination/dir', $linkConfig->getDestinationDir());

        // Create package to check supports methods, package has name that
        // matches the for package name passed to link config constructor
        $package = new Package('test/package', '1.0.0', '1');
        $this->assertTrue($linkConfig->supports($package));

        // Create expected unsupported package
        $package = new Package('unsupported/package', '1.0.0', '1');
        $this->assertFalse($linkConfig->supports($package));
    }
}
