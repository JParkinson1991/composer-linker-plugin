<?php
/**
 * @file
 * LinkDefinitionFactoryInterface.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Link;

use Composer\Package\PackageInterface;

/**
 * Interface LinkDefinitionFactoryInterface
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Link
 */
interface LinkDefinitionFactoryInterface
{
    /**
     * Creates a link definition object for the given package using the
     * associated config data found in the root package
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition
     */
    public function createForPackage(PackageInterface $package): LinkDefinition;
}
