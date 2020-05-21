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
     * Returns whether orphan directories of linked files should be deleted
     *
     * @return bool
     */
    public function getDeleteOrphanDirs(): bool;

    /**
     * Returns the destination directory for linked files
     *
     * @return string
     */
    public function getDestinationDir(): string;

    /**
     * Returns the package being linked in this definition
     *
     * @return \Composer\Package\PackageInterface
     */
    public function getPackage(): PackageInterface;

    /**
     * Does the link definition use copy for linking files.
     *
     * @return bool
     */
    public function getCopyFiles(): bool;

    /**
     * Returns file mappings set against the link definition.
     *
     * Raw file mappings are processed and restructured so that the returned
     * array has the following format:
     *     key => source file
     *     value => array of destinations
     *
     * @return array
     */
    public function getFileMappings(): array;
}
