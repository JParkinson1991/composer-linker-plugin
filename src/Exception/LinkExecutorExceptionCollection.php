<?php
/**
 * @file
 * LinkExecutorExceptionCollection.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Exception;

use Exception;

/**
 * Class LinkExecutorExceptionCollection
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Exception
 */
class LinkExecutorExceptionCollection extends Exception
{
    /**
     * Holds all of the exceptions belonging to the collection
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException[]
     */
    protected $exceptions = [];

    /**
     * Adds an exception to this collection
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException $exception
     *
     * @return void
     */
    public function addException(LinkExecutorException $exception): void
    {
        $this->exceptions[] = $exception;
    }

    /**
     * Returns all exceptions stored within the collection
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException[]
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    /**
     * Returns boolean indication on whether the collection contains any
     * exceptions.
     *
     * @return bool
     */
    public function hasExceptions(): bool
    {
        return (count($this->exceptions) > 0);
    }
}
