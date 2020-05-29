<?php
/**
 * @file
 * LinkFileHandler.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Link;

use Composer\Installer\InstallationManager;
use Composer\Util\Filesystem as ComposerFileSystem;
use Exception;
use InvalidArgumentException;
use JParkinson1991\ComposerLinkerPlugin\Log\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\Filesystem\Filesystem as SymfonyFileSystem;

/**
 * Class LinkFileHandler
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Link
 */
class LinkFileHandler implements LinkFileHandlerInterface, LoggerAwareInterface
{
    /**
     * Shared logger functionality
     */
    use LoggerAwareTrait;

    /**
     * The local composer file system instance
     *
     * @var \Composer\Util\Filesystem
     */
    protected $composerFileSystem;

    /**
     * The local installation manager instance
     *
     * @var \Composer\Installer\InstallationManager
     */
    protected $installationManager;

    /**
     * The root path used for any non absolute paths encountered by this class
     *
     * @var string
     */
    protected $rootPath;

    /**
     * The local symfony filesystem instance
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $symfonyFileSystem;

    /**
     * LinkFileHandler constructor.
     *
     * @param \Symfony\Component\Filesystem\Filesystem $filesystem
     * @param \Composer\Installer\InstallationManager $installationManager
     */
    public function __construct(
        SymfonyFileSystem $symfonyFileSystem,
        ComposerFilesystem $composerFileSystem,
        InstallationManager $installationManager
    ) {
        $rootPath = realpath(getcwd());
        if (!is_string($rootPath)) {
            throw new Exception('Failed to determine root path from current working dir');
        }

        $this->symfonyFileSystem = $symfonyFileSystem;
        $this->composerFileSystem = $composerFileSystem;
        $this->installationManager = $installationManager;
        $this->rootPath = $rootPath;
    }

    /**
     * Sets the root directory to resolve relative paths from
     *
     * @param string $rootPath
     *     The root directory to set
     *
     * @return void
     */
    public function setRootPath(string $rootPath): void
    {
        if (!file_exists($rootPath) || !is_dir($rootPath)) {
            throw new InvalidArgumentException(sprintf(
                'Ensure %s exists and is directory',
                $rootPath
            ));
        }

        $this->rootPath = $rootPath;
    }

    /**
     * Links files for a package as configured using the passed link
     * definition.
     *
     * This method determines whether link definition is for the entire
     * package directory or not and routes to relevant sub method
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionInterface $linkDefinition
     *
     * @throws \Exception
     */
    public function link(LinkDefinitionInterface $linkDefinition): void
    {
        if (empty($linkDefinition->getFileMappings())) {
            $this->linkDir($linkDefinition);
        }
        else {
            $this->linkFiles($linkDefinition);
        }
    }

    /**
     * Unlinks files for a package as configured using the passed link
     * definition
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionInterface $linkDefinition
     *
     * @throws \Exception
     */
    public function unlink(LinkDefinitionInterface $linkDefinition): void
    {
        if (empty($linkDefinition->getFileMappings())) {
            $this->unlinkDir($linkDefinition);
        }
        else {
            $this->unlinkFiles($linkDefinition);
        }

        if ($linkDefinition->getDeleteOrphanDirs() === true) {
            $this->deleteOrphanDirectories($linkDefinition);
        }
    }

    /**
     * Links an entire package directory as configured by the link definition
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionInterface $linkDefinition
     *
     * @throws \Exception
     */
    protected function linkDir(LinkDefinitionInterface $linkDefinition): void
    {
        $sourceRoot = $this->installationManager->getInstallPath($linkDefinition->getPackage());
        $destRoot = $this->getAbsolutePath($linkDefinition->getDestinationDir(), $this->rootPath);

        try {
            if ($linkDefinition->getCopyFiles()) {
                $this->symfonyFileSystem->mirror($sourceRoot, $destRoot);
                $this->logInfo('Copied '.$sourceRoot.' to '.$destRoot);
            }
            else {
                $this->symfonyFileSystem->symlink($sourceRoot, $destRoot);
                $this->logInfo('Symlinked '.$sourceRoot.' to '.$destRoot);
            }
        }
        catch (Exception $e) {
            $this->logError(sprintf(
                'Failed to link %s to %s. Got error: %s',
                $sourceRoot,
                $destRoot,
                $e->getMessage()
            ));

            throw $e;
        }
    }

    /**
     * Links only the mapped files as configured by the link definition
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionInterface $linkDefinition
     */
    protected function linkFiles(LinkDefinitionInterface $linkDefinition): void
    {
        $sourceRoot = $this->installationManager->getInstallPath($linkDefinition->getPackage());
        $destRoot = $this->getAbsolutePath($linkDefinition->getDestinationDir(), $this->rootPath);

        foreach ($linkDefinition->getFileMappings() as $sourceFile => $destinations) {
            // Get an absolute path to the source file from the source root
            $sourceFilePath = $this->getAbsolutePath($sourceFile, $sourceRoot);

            foreach ($destinations as $destinationFile) {
                $destFilePath = $this->getAbsolutePath($destinationFile, $destRoot);

                try {
                    if ($linkDefinition->getCopyFiles()) {
                        $this->symfonyFileSystem->copy($sourceFilePath, $destFilePath);
                        $this->logInfo('Copied '.$sourceFilePath.' to '.$destFilePath);
                    }
                    else {
                        $this->symfonyFileSystem->symlink($sourceFilePath, $destFilePath);
                        $this->logInfo('Symlinked '.$sourceFilePath.' to '.$destFilePath);
                    }
                }
                catch (Exception $e) {
                    $this->logError(sprintf(
                        'Failed to link %s to %s. Got error: %s',
                        $sourceFilePath,
                        $destFilePath,
                        $e->getMessage()
                    ));

                    throw $e;
                }
            }
        }
    }

    /**
     * Unlinks an entire package directory as configure by the link definition
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionInterface $linkDefinition
     *
     * @throws \Exception
     */
    protected function unlinkDir(LinkDefinitionInterface $linkDefinition): void
    {
        $deletePath = $this->getAbsolutePath($linkDefinition->getDestinationDir(), $this->rootPath);

        try {
            $this->symfonyFileSystem->remove($deletePath);
            $this->logInfo('Deleted '.$deletePath);
        }
        catch (Exception $e) {
            $this->logError(sprintf(
                'Failed to unlink %s. Got error %s',
                $deletePath,
                $e->getMessage()
            ));

            throw $e;
        }
    }

    /**
     * Unlinks only the mapped files for a package as configured by the link
     * definition
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionInterface $linkDefinition
     */
    protected function unlinkFiles(LinkDefinitionInterface $linkDefinition): void
    {
        $destRoot = $this->getAbsolutePath($linkDefinition->getDestinationDir(), $this->rootPath);

        foreach ($linkDefinition->getFileMappings() as $source => $destinations) {
            foreach ($destinations as $destFile) {
                $destFilePath = $this->getAbsolutePath($destFile, $destRoot);

                try {
                    $this->symfonyFileSystem->remove($destFilePath);
                    $this->logInfo('Deleted '.$destFilePath);
                }
                catch (Exception $e) {
                    $this->logError(sprintf(
                        'Failed to unlink %s. Got error %s',
                        $destFilePath,
                        $e->getMessage()
                    ));

                    throw $e;
                }
            }
        }
    }

    /**
     * Deletes any orphan directories for a link definition's linked directory
     * or files.
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionInterface $linkDefinition
     *
     * @return void
     */
    protected function deleteOrphanDirectories(LinkDefinitionInterface $linkDefinition): void
    {
        // Build absolute path to root destination directory for link definition
        $destRoot = $this->getAbsolutePath($linkDefinition->getDestinationDir(), $this->rootPath);

        // Build the search start paths for the link definition
        $searchPaths = [];
        if (!empty($mappedFiles = $linkDefinition->getFileMappings())) {
            foreach ($mappedFiles as $source => $destinations) {
                foreach ($destinations as $destination) {
                    $destinationFilePath = $this->getAbsolutePath($destination, $destRoot);
                    $destinationFileDirectory = dirname($destinationFilePath);

                    if (!in_array($destinationFileDirectory, $searchPaths)) {
                        $searchPaths[] = $destinationFileDirectory;
                    }
                }
            }
        }
        $searchPaths[] = $destRoot; // Add the destination root last

        // Process each of the search paths remove any orphan directories
        // up until the root path of this file handler instance. Do not
        // attempt to remove orphan directories using absolute paths outside
        // of this file handler's root path, too risky, not worth it.
        foreach ($searchPaths as $searchPath) {
            $searchDir = $searchPath;

            // While conditions ensure we never leave root path
            // every checked path must start with the link handlers root path
            // but it must not be the root path
            while (
                strpos($searchDir, $this->rootPath) === 0
                && rtrim($searchDir, '/') !== rtrim($this->rootPath, '/')
            ) {
                // If the directory still exists, it may have already been
                // removed as part of previous orphan cleanup search start
                // path processing
                if (is_dir($searchDir)) {
                    // Make sure the directory if empty, if not break the process
                    // breaking process stops parent recursion up to root path
                    $searchDirIsEmpty = !(new \FilesystemIterator($searchDir))->valid();
                    if ($searchDirIsEmpty === false) {
                        break;
                    }

                    try {
                        // Remove the directory, log whats happened,
                        $this->symfonyFileSystem->remove($searchDir);
                        $this->logInfo('Deleted orphan directory: '.$searchDir);
                    }
                    catch (Exception $e) {
                        $this->logWarning(sprintf(
                            'Failed deleting orphan directory: %s. Got error: %s',
                            $searchDir,
                            $e->getMessage()
                        ));

                        // Breaks parent recursion up to root path and starts
                        // processing from next search path
                        break;
                    }
                }

                // Set search dir to the parent for next loop
                // Do this regardless of checked directory existing to ensure
                // no orphan parents missed
                $searchDir = dirname($searchDir);
            }
        }
    }

    /**
     * Returns an absolute path for the given path.
     *
     * @param string $path
     *     The path to return in it's absolute form
     * @param string $resolveFromPath
     *     If $path is relative, resolve it from this path
     *
     * @return string
     */
    protected function getAbsolutePath(string $path, string $resolveFromPath): string
    {
        return $this->symfonyFileSystem->isAbsolutePath($path)
            ? $path
            : $this->composerFileSystem->normalizePath($resolveFromPath.'/'.ltrim($path, '/'));
    }
}
