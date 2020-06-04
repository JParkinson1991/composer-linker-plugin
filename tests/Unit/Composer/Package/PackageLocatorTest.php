<?php
/**
 * @file
 * PackageLocatorTest.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Package;

use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use InvalidArgumentException;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageLocator;
use PHPUnit\Framework\TestCase;

/**
 * Class PackageLocatorTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Package
 */
class PackageLocatorTest extends TestCase
{
    /**
     * Tests the locator can find a package in a given repository
     *
     * @return void
     */
    public function testItGetsAPackage(): void
    {
        $testPackage = $this->createMock(PackageInterface::class);

        $repository = $this->createMock(RepositoryInterface::class);
        $repository
            ->method('findPackages')
            ->with('test/package')
            ->willReturn([$testPackage]);

        $packageLocator = new PackageLocator();
        $package = $packageLocator->getFromRepository('test/package', $repository);

        $this->assertInstanceOf(PackageInterface::class, $package);
        $this->assertSame($testPackage, $package);
    }

    /**
     * Tests the locator throws an exception if a package is not found
     *
     * @return void
     */
    public function testItThrowsExcetpionIfPackageNotFound(): void
    {
        $repository = $this->createMock(RepositoryInterface::class);
        $repository
            ->method('findPackages')
            ->with('test/package')
            ->willReturn([]);

        $packageLocator = new PackageLocator();

        $this->expectException(InvalidArgumentException::class);
        $packageLocator->getFromRepository('test/package', $repository);
    }

    /**
     * Tests the locator throws an exception if multiple packages are found
     * using a name.
     *
     * Due to the way in which the locator uses underlying services to provide
     * a simple interface there may be cases where multiple packages are found
     * if the given name is not precise enough. That is, if only a vendor name
     * is given, all packages for that vendor will be returned.
     *
     * @return void
     */
    public function testItThrowsExceptionsIfMultiplePackagesFound(): void
    {
        $repository = $this->createMock(RepositoryInterface::class);
        $repository
            ->method('findPackages')
            ->with('doctrine')
            ->willReturn([
                $this->createMock(PackageInterface::class), // orm
                $this->createMock(PackageInterface::class), // common
                $this->createMock(PackageInterface::class) // annotations etc
            ]);

        $packageLocator = new PackageLocator();

        $this->expectException(InvalidArgumentException::class);
        $packageLocator->getFromRepository('doctrine', $repository);
    }
}
