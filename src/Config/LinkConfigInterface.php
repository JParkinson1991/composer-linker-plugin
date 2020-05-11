<?php
/**
 * @file
 * PackageLinkConfigInterface.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Config;

use Composer\Package\PackageInterface;

/**
 * Interface PackageLinkConfigInterface
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Config
 */
interface LinkConfigInterface
{
    /**
     * Returns the destination directory of the package link
     *
     * @return string
     */
    public function getDestinationDir(): string;

    /**
     * Returns boolean indication on whether this config supports the
     * given package. Ie. Is this config for that package
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return bool
     *     true - supports
     *     false - unsupported
     */
    public function supports(PackageInterface $package): bool;
}
