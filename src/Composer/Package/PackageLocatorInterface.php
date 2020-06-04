<?php
/**
 * @file
 * PackageLocatorInterface.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Package;

use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;

/**
 * Interface PackageLocatorInterface
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Composer\Package
 */
interface PackageLocatorInterface
{
    /**
     * Returns a package from a given repository by name
     *
     * @param string $packageName
     *     The name of the package
     * @param \Composer\Repository\RepositoryInterface $repository
     *     The repository to get the package from
     *
     * @return \Composer\Package\PackageInterface
     *     The found package
     *
     * @throws \InvalidArgumentException
     *     If the package not found
     */
    public function getFromRepository(string $packageName, RepositoryInterface $repository): PackageInterface;
}
