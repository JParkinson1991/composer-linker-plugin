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
interface PackageLinkConfigInterface
{
    /**
     * Package support determinator
     *
     * @param \Composer\Package\PackageInterface $package
     *     The package in question.
     *     Does this config work with this package?
     *
     *
     * @return bool
     */
    public function supports(PackageInterface $package): bool;
}
