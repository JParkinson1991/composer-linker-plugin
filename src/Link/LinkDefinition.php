<?php
/**
 * @file
 * LinkDefinition.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Link;

use Composer\Package\PackageInterface;

/**
 * Class LinkDefinition
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Link
 */
class LinkDefinition implements LinkDefinitionInterface
{
    /**
     * The destination directory of the package's linked files
     *
     * @var string
     */
    protected $destinationDir;

    /**
     * The package being linked in this definition
     *
     * @var \Composer\Package\PackageInterface
     */
    protected $package;

    /**
     * Should linked files by copied
     *
     * @var bool
     */
    protected $copyFiles = false;

    /**
     * LinkDefinition constructor.
     *
     * @param \Composer\Package\PackageInterface $package
     * @param string $destinationDir
     */
    public function __construct(PackageInterface $package, string $destinationDir)
    {
        $this->package = $package;
        $this->destinationDir = $destinationDir;
    }

    /**
     * Returns the destination directory for linked files
     *
     * @return string
     */
    public function getDestinationDir(): string
    {
        return $this->destinationDir;
    }

    /**
     * Returns the package being linked in this definition
     *
     * @return \Composer\Package\PackageInterface
     */
    public function getPackage(): PackageInterface
    {
        return $this->package;
    }

    /**
     * Does the link definition use copy for linking files.
     *
     * @return bool
     */
    public function getCopyFiles(): bool
    {
        return $this->copyFiles;
    }

    /**
     * Sets whether link definition should copy linked files
     *
     * @param bool $copyFiles
     */
    public function setCopyFiles(bool $copyFiles): void
    {
        $this->copyFiles = $copyFiles;
    }
}
