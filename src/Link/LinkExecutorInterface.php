<?php
/**
 * @file
 * LinkExecutorInterface.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Link;

use Composer\Package\PackageInterface;

/**
 * Interface LinkExecutorInterface
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Link
 */
interface LinkExecutorInterface
{
    /**
     * Links a given package.
     *
     * Builds a link definition before passing it to the file handler
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return void
     */
    public function linkPackage(PackageInterface $package): void;

    /**
     * Unlinks a given package.
     *
     * Builds a link definition before passing it to the file handler
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return void
     */
    public function unlinkPackage(PackageInterface $package): void;
}
