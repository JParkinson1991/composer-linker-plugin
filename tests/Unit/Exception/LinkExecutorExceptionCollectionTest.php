<?php
/**
 * @file
 * LinkExecutorExceptionCollection.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Exception;

use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorExceptionCollection;
use PHPUnit\Framework\TestCase;

/**
 * Class LinkExecutorExceptionCollection
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Exception
 */
class LinkExecutorExceptionCollectionTest extends TestCase
{
    /**
     * Test exceptions can be added to the collection as expected
     *
     * @return void
     */
    public function testExceptionsCanBeAdded(): void
    {
        $linkExecutorExceptionCollection = new LinkExecutorExceptionCollection();

        $linkExecutorExceptionCollection->addException(
            $this->createMock(LinkExecutorException::class)
        );

        $this->assertTrue($linkExecutorExceptionCollection->hasExceptions());
        $this->assertCount(1, $linkExecutorExceptionCollection->getExceptions());

        $linkExecutorExceptionCollection->addException(
            $this->createMock(LinkExecutorException::class)
        );

        $this->assertCount(2, $linkExecutorExceptionCollection->getExceptions());
    }
}
