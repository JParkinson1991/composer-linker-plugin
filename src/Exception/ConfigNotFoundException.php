<?php
/**
 * @file
 * ConfigNotFoundException.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Exception;

use Composer\Package\PackageInterface;
use Exception;

/**
 * Class ConfigNotFoundException
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Exception
 */
class ConfigNotFoundException extends Exception
{
    /**
     * Returns not found exception when package config could not be found
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     */
    public static function forPackage(PackageInterface $package): ConfigNotFoundException
    {
        return new self(sprintf(
            'No config found for package %s',
            $package->getName()
        ));
    }
}
