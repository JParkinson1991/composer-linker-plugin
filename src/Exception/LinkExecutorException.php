<?php
/**
 * @file
 * LinkExecutorException.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Exception;

use Composer\Package\PackageInterface;
use Exception;
use Throwable;

/**
 * Class LinkExecutorException
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Exception
 */
class LinkExecutorException extends Exception
{
    /**
     * Holds the package that caused the exception
     *
     * @var PackageInterface
     */
    protected $package;

    /**
     * Holds the exception triggered when running link execution for the
     * $package
     *
     * @var Throwable
     */
    protected $executionException;

    /**
     * LinkExecutorException constructor.
     *
     * @param \Composer\Package\PackageInterface $package
     * @param \Throwable $executionException
     * @param \Throwable|null $previous
     */
    public function __construct(PackageInterface $package, Throwable $executionException, Throwable $previous = null)
    {
        $message = sprintf(
            'Execution exception for: %s. (%s) %s',
            $package->getName(),
            get_class($executionException),
            $executionException->getMessage()
        );

        $this->package = $package;
        $this->executionException = $executionException;

        parent::__construct($message, (int)$executionException->getCode(), $previous);
    }

    /**
     * Returns the package causing the link execution exception
     *
     * @return \Composer\Package\PackageInterface
     */
    public function getPackage(): PackageInterface
    {
        return $this->package;
    }

    /**
     * Returns the actual exception triggered when trying to executing
     * linking for the package
     *
     * @return \Throwable
     */
    public function getExecutionException(): Throwable
    {
        return $this->executionException;
    }
}
