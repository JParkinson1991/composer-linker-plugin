<?php
/**
 * @file
 * ConfigInterface.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Config;

use Composer\Package\PackageInterface;

/**
 * Interface ConfigInterface
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Config
 */
interface ConfigInterface
{
    /**
     * Checks if a package has mapping config associated with it
     *
     * @param PackageInterface $package
     *     The package to check
     *
     * @return bool
     */
    public function packageHasConfig(PackageInterface $package): bool;

    /**
     * Returns the linked directory for a package.
     *
     * @param PackageInterface $package
     *     The package to get the link dir for
     *
     * @return string|null
     */
    public function getPackageLinkDir(PackageInterface $package): ?string;

    /**
     * Returns the specific files to link for a package.
     *
     * @param PackageInterface $package
     *     The package to get the linked files for
     *
     * @return string[]|null
     */
    public function getPackageLinkFiles(PackageInterface $package): ?array;

    /**
     * Returns whether linking should occur via copy or symlink for a package.
     *
     * @param PackageInterface $package
     *     The package to check the copy config for
     *
     * @return bool
     */
    public function packageUsesCopy(PackageInterface $package): bool;

}