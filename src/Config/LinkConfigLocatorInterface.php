<?php
/**
 * @file
 * ConfigLocatorInterface.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Config;

use Composer\Package\RootPackageInterface;

/**
 * Interface ConfigLocatorInterface
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Config
 */
interface LinkConfigLocatorInterface
{
    /**
     * Locate all configuration stored under a given key within the composer
     * root package
     *
     * @param \Composer\Package\RootPackageInterface $rootPackage
     * @param string $configKey
     *
     * @return mixed
     */
    public function locateInRootPackage(RootPackageInterface $rootPackage, string $configKey);
}
