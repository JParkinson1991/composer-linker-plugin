<?php
/**
 * @file
 * PackageLinkConfigTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Config;

use Composer\Package\Package;
use JParkinson1991\ComposerLinkerPlugin\Config\PackageLinkConfig;
use PHPUnit\Framework\TestCase;

/**
 * Class PackageLinkConfigTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Config
 */
class PackageLinkConfigTest extends TestCase
{
    /**
     * Tests that package configuration classes can properly determine which
     * packages they support.
     *
     */
    public function testSupportValidationForPackages(): void
    {
        $packageConfig = new PackageLinkConfig('test/package', 'destination/directory');

        $supportedPackage = $this->createStub(Package::class);
        $supportedPackage->method('getName')
            ->willReturn('test/package');

        $this->assertTrue($packageConfig->supports($supportedPackage));

        $unsupportedPackage = $this->createStub(Package::class);
        $supportedPackage->method('getName')
            ->willReturn('unsupported/package');

        $this->assertFalse($packageConfig->supports($unsupportedPackage));
    }
}
