<?php
/**
 * @file
 * LinkDefinitionInterface.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Link;

use Composer\Package\PackageInterface;

/**
 * Interface LinkDefinitionInterface
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Link
 */
interface LinkDefinitionInterface
{
    /**
     * Returns the destination directory for linked files
     *
     * @return string
     */
    public function getDestinationDir();

    /**
     * Returns the package being linked in this definition
     *
     * @return \Composer\Package\PackageInterface
     */
    public function getPackage();

    /**
     * Does the link definition use copy for linking files.
     *
     * @return bool
     */
    public function getCopyFiles();
}
