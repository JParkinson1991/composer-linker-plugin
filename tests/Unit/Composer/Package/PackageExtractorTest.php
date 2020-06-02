<?php
/**
 * @file
 * PackageExtractorTest.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Package;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\Package\Package;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Class PackageExtractorTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Package
 */
class PackageExtractorTest extends TestCase
{
    /**
     * @var \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractor
     */
    protected $packageExtractor;

    /**
     * @var \Composer\Package\PackageInterface
     */
    protected $testPackage;

    /**
     * Sets up this class prior to running each test case
     */
    public function setUp(): void
    {
        $this->packageExtractor = new PackageExtractor();
        $this->testPackage = new Package('test/package', '1.0.0', '1');
    }

    /**
     * Tests packages can be extracted as expected from package events for
     * install operations
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function testItExtractsPackageFromInstallEvent()
    {
        $event = $this->createPackageEventForOperation(InstallOperation::class);

        $package = $this->packageExtractor->extractFromEvent($event);

        $this->assertSame($package, $this->testPackage);
    }

    /**
     * Tests packages can be extracted as expected from package events for
     * update operations
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function testItExtractsPackagesFromUpdateEvents()
    {
        $event = $this->createPackageEventForOperation(UpdateOperation::class, 'getTargetPackage');

        $package = $this->packageExtractor->extractFromEvent($event);

        $this->assertSame($package, $this->testPackage);
    }

    /**
     * Tests packages can be extracted as expected from package events for
     * uninstall operations
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function testItExtractsPackagesFromUninstallEvents()
    {
        $event = $this->createPackageEventForOperation(UninstallOperation::class);

        $package = $this->packageExtractor->extractFromEvent($event);

        $this->assertSame($package, $this->testPackage);
    }

    public function testItThrowsExceptionOnUnhandledPackageEventOperation()
    {
        // Create mock that returns no operation
        $event = $this->createMock(PackageEvent::class);

        $this->expectException(PackageExtractionUnhandledEventOperationException::class);
        $this->packageExtractor->extractFromEvent($event);
    }

    /**
     * Mocks a package event for a given operation returning the test
     * package
     *
     * @param string $operationClass
     *
     * @return \Composer\Installer\PackageEvent|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function createPackageEventForOperation(string $operationClass, string $methodName = 'getPackage')
    {
        $operation = $this->createMock($operationClass);
        $operation->method($methodName)
            ->willReturn($this->testPackage);

        $event = $this->createMock(PackageEvent::class);
        $event->method('getOperation')
            ->willReturn($operation);

        return $event;
    }
}
