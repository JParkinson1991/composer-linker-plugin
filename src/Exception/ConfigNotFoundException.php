<?php
/**
 * @file
 * ConfigNotFoundException.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Exception;

use Composer\Package\PackageInterface;

/**
 * Class ConfigNotFoundException
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Exception
 */
class ConfigNotFoundException extends \Exception
{
    /**
     * Returns not found exception when package config could not be found
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     */
    public static function forPackage(PackageInterface $package)
    {
        return new self(sprintf(
            'No config found for package %s',
            $package->getName()
        ));
    }
}
