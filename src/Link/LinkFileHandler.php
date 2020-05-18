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
class LinkFileHandler implements LoggerAwareInterface
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
     * @var $rootPath
     */
    protected $rootPath;

    /**
     * The local symfony filesystem instance
     *
     * @var \Symfony\Component\Filesystem\Filesystem;
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
        $this->symfonyFileSystem = $symfonyFileSystem;
        $this->composerFileSystem = $composerFileSystem;
        $this->installationManager = $installationManager;
        $this->rootPath = realpath(getcwd());
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
        $this->linkDir($linkDefinition);
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
        $this->unlinkDir($linkDefinition);
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
        $destRoot = $this->getAbsolutePath($linkDefinition->getDestinationDir());

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
     * Unlinks an entire package directory as configure by the link definition
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionInterface $linkDefinition
     *
     * @throws \Exception
     */
    protected function unlinkDir(LinkDefinitionInterface $linkDefinition): void
    {
        $deletePath = $this->getAbsolutePath($linkDefinition->getDestinationDir());

        try {
            $this->symfonyFileSystem->remove($deletePath);
            $this->logInfo('Deleted '.$deletePath);
        }
        catch (Exception $e) {
            $this->logError(sprintf(
                'Failed to delete %s. Got error %s',
                $deletePath,
                $e->getMessage()
            ));

            throw $e;
        }
    }

    /**
     * Returns an absolute path for the given path.
     *
     * @param string $path
     *     If already absolute returned as it
     *     If relative, root path is appended to it
     *
     * @return string
     */
    protected function getAbsolutePath(string $path): string
    {
        return $this->symfonyFileSystem->isAbsolutePath($path)
            ? $path
            : $this->composerFileSystem->normalizePath($this->rootPath.'/'.ltrim($path, '/'));
    }
}
