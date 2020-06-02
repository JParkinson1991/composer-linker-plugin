<?php
/**
 * @file
 * LinkDefinition.php
 */

declare(strict_types=1);

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
     * Should linked files by copied
     *
     * @var bool
     */
    protected $copyFiles = false;

    /**
     * Should orphan directories of linked files be deleted
     *
     * @var bool
     */
    protected $deleteOrphans = false;

    /**
     * The destination directory of the package's linked files
     *
     * @var string
     */
    protected $destinationDir;

    /**
     * Specific file mappings registry.
     *
     * One source file can have multiple destinations. To enforce this via a
     * single array simply use the destinations as array keys and the source
     * file as the array value.
     *
     * If the array key already exists for a destination when trying to add a
     * new its clear that their is an override and an exception can be thrown
     * as needed,
     *
     * @var array
     *     key = destination
     *           If relative, resolve from destination dir
     *           If absolute treat as it
     *     value = source file
     *           Relative to the package's installation directory
     */
    protected $fileMappings = [];

    /**
     * The package being linked in this definition
     *
     * @var \Composer\Package\PackageInterface
     */
    protected $package;

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
     *
     * @return void
     */
    public function setCopyFiles(bool $copyFiles): void
    {
        $this->copyFiles = $copyFiles;
    }

    /**
     * Returns whether orphan directories of linked files should be deleted
     *
     * @return bool
     */
    public function getDeleteOrphanDirs(): bool
    {
        return $this->deleteOrphans;
    }

    /**
     * Set whether orphan directories of link files should be deleted
     *
     * @param bool $deleteOrphans
     *
     * @return void
     */
    public function setDeleteOrphanDirs(bool $deleteOrphans): void
    {
        $this->deleteOrphans = $deleteOrphans;
    }

    /**
     * Adds a file mapping to the link definition
     *
     * @param string $source
     *     The source file, relative to the package install root
     * @param string $destination
     *     The destination for the file
     *     If relative, should be resolved from link destination dir
     *     If absolute treat as is
     *
     * @return void
     */
    public function addFileMapping(string $source, string $destination): void
    {
        // If a destination already set for a file mapping and the source files
        // are different, ie. this is a not a duplicated mapping for the same
        // files, throw the exception
        if (array_key_exists($destination, $this->fileMappings) && $this->fileMappings[$destination] !== $source) {
            throw new \InvalidArgumentException(sprintf(
                '%s already defined as a destination for mapping with source %s',
                $destination,
                $this->fileMappings[$destination]
            ));
        }

        $this->fileMappings[$destination] = $source;
    }

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
    public function getFileMappings(): array
    {
        $processed = [];
        foreach ($this->fileMappings as $destination => $source) {
            if (empty($processed[$source])) {
                $processed[$source] = [];
            }

            $processed[$source][] = $destination;
        }

        return $processed;
    }
}
