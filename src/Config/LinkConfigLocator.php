<?php
/**
 * @file
 * ConfigLocator.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Config;

use Composer\Package\RootPackageInterface;
use JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException;

/**
 * Class ConfigLocator
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Config
 */
class LinkConfigLocator implements LinkConfigLocatorInterface
{
    /**
     * @inheritDoc
     */
    public function locateInRootPackage(RootPackageInterface $rootPackage, string $configKey)
    {
        $extraConfig = $rootPackage->getExtra();
        if (!array_key_exists($configKey, $extraConfig)) {
            throw ConfigNotFoundException::atKey($configKey);
        }

        return $extraConfig[$configKey];
    }
}
