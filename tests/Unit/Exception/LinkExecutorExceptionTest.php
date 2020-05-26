<?php
/**
 * @file
 * LinkExecutorExceptionTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Exception;

use Composer\Package\PackageInterface;
use Exception;
use InvalidArgumentException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException;
use PHPUnit\Framework\TestCase;

/**
 * Class LinkExecutorExceptionTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Exception
 */
class LinkExecutorExceptionTest extends TestCase
{
    /**
     * Tests instantiation works as expected
     *
     * @return void
     */
    public function testInstantiation(): void
    {
        $package = $this->createMock(PackageInterface::class);
        $exception = $this->createMock(Exception::class);

        $linkExecutorException = new LinkExecutorException($package, $exception);

        $this->assertSame($package, $linkExecutorException->getPackage());
        $this->assertSame($exception, $linkExecutorException->getExecutionException());
    }

    /**
     * Given that this exception wraps an another for a package context, ensure
     * the generated exception is meaningful
     *
     * @return void
     */
    public function testItCreatesAMeaningfulMessage(): void
    {
        $package = $this->createMock(PackageInterface::class);
        $package
            ->method('getName')
            ->willReturn('test/package');

        $exception = $this->createMock(InvalidArgumentException::class);

        $linkExecutorException = new LinkExecutorException($package, $exception);

        // Ensure package name somewhere in message
        $this->assertStringContainsStringIgnoringCase(
            'test/package',
            $linkExecutorException->getMessage(),
            'Exception message should contain the name of the package'
        );

        // Ensure exception type somewhere in message
        $this->assertStringContainsStringIgnoringCase(
            InvalidArgumentException::class,
            $linkExecutorException->getMessage(),
            'Exception message should contain the type of wrapped exception'
        );
    }
}
