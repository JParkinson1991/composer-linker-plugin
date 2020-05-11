<?php
/**
 * @file
 * LinkFileHandlerUnsupportedPackageException.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Files;

use Composer\Package\PackageInterface;

/**
 * Class LinkFileHandlerUnsupportedPackageException
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Files
 */
class LinkFileHandlerUnsupportedPackageException extends \Exception
{
    /**
     * Creates an instance of itself using the unsupported package
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return static
     */
    public static function fromPackage(PackageInterface $package): self
    {
        return new self($package->getName().' not supported.');
    }

}
