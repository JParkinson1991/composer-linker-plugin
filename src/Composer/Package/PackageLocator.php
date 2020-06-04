<?php
/**
 * @file
 * PackageLocator.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Package;

use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use InvalidArgumentException;

/**
 * Class PackageLocator
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Composer\Package
 */
class PackageLocator implements PackageLocatorInterface
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
    public function getFromRepository(string $packageName, RepositoryInterface $repository): PackageInterface
    {
        $foundPackages = $repository->findPackages($packageName);

        if (count($foundPackages) === 0) {
            throw new InvalidArgumentException(
                'Failed to find package <info>'.$packageName.'</info>'
            );
        }

        if (count($foundPackages) > 1) {
            throw new InvalidArgumentException(
                'Found multiple packages for <info>'.$packageName.'</info>. Be more specific'
            );
        }

        return $foundPackages[0];
    }
}
