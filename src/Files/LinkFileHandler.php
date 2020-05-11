<?php
/**
 * @file
 * LinkFileHandler.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Files;

use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use JParkinson1991\ComposerLinkerPlugin\Config\LinkConfigInterface;
use JParkinson1991\ComposerLinkerPlugin\Log\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class LinkFileHandler
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Files
 */
class LinkFileHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * The file system instance
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fileSystem;

    /**
     * The local installation manager instance
     *
     * @var \Composer\Installer\InstallationManager
     */
    protected $installationManager;

    /**
     * The link configs used to determine how files are handled for a given
     * package.
     *
     * Link configs are stored in a multi dimensional array, key'd by the
     * name of the package they support
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Config\LinkConfigInterface[]
     */
    protected $linkConfigs = [];

    /**
     * The root path used for any non absolute paths encountered by this class
     *
     * @var string
     */
    protected $rootPath;

    /**
     * LinkFileHandler constructor.
     *
     * @param \Symfony\Component\Filesystem\Filesystem $fileSystem
     * @param \Composer\Installer\InstallationManager $installationManager
     */
    public function __construct(Filesystem $fileSystem, InstallationManager $installationManager)
    {
        $this->fileSystem = $fileSystem;
        $this->installationManager = $installationManager;
        $this->rootPath = realpath(getcwd());
    }

    /**
     * Adds a link config to the instances
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Config\LinkConfigInterface $packageLinkConfig
     */
    public function addConfig(LinkConfigInterface $packageLinkConfig): void
    {
        $this->linkConfigs[spl_object_id($packageLinkConfig)] = $packageLinkConfig;
    }

    /**
     * Returns indication on whether the file handler instance supports the
     * given package
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return bool
     */
    public function supportsPackage(PackageInterface $package)
    {
        try {
            $this->getSupportingConfigs($package);

            return true;
        } catch (LinkFileHandlerUnsupportedPackageException $e) {
            return false;
        }
    }

    /**
     * Handles linking of package's files using supporting stored on the
     * object instance
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Files\LinkFileHandlerUnsupportedPackageException
     */
    public function link(PackageInterface $package, bool $skipErrors = true): void
    {
        // Get supporting configs first, if non found exception thrown
        // halting process
        $supportingConfigs = $this->getSupportingConfigs($package);

        // Get package install path
        $packageInstallPath = $this->installationManager->getInstallPath($package);

        // Process package as required
        foreach ($supportingConfigs as $config) {
            $destinationPath = $this->getAbsolutePath($config->getDestinationDir());

            try {
                $this->fileSystem->symlink($packageInstallPath, $destinationPath);

                $this->logInfo(sprintf(
                    'Symlinked %s to %s',
                    $packageInstallPath,
                    $destinationPath
                ));
            } catch (\Exception $e) {
                $this->logError(sprintf(
                    'Failed to symlink %s to %s. Got error: %s',
                    $packageInstallPath,
                    $destinationPath,
                    $e->getMessage()
                ));

                if ($skipErrors === false) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Handles removal of a package's linked files as defined by the link
     * configs stored on the object instance.
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Files\LinkFileHandlerUnsupportedPackageException
     */
    public function unlink(PackageInterface $package, bool $skipErrors = true): void
    {
        // Get the package install patch
        foreach ($this->getSupportingConfigs($package) as $config) {
            $deletePath =  $this->getAbsolutePath($config->getDestinationDir());

            try {
                $this->fileSystem->remove($deletePath);
                $this->logInfo('Deleted '.$deletePath);
            } catch (\Exception $e) {
                $this->logError(sprintf(
                    'Failed to delete %s. Got error %s',
                    $deletePath,
                    $e->getMessage()
                ));

                if ($skipErrors === false) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Gets all supporting config objects for the given package
     *
     * @param \Composer\Package\PackageInterface $package
     *     The package to get configs for
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Config\LinkConfigInterface[]
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Files\LinkFileHandlerUnsupportedPackageException
     */
    protected function getSupportingConfigs(PackageInterface $package): array
    {
        $supportingConfigs = [];
        foreach ($this->linkConfigs as $config) {
            if ($config->supports($package)) {
                $supportingConfigs[] = $config;
            }
        }

        if (count($supportingConfigs) === 0) {
            throw LinkFileHandlerUnsupportedPackageException::fromPackage($package);
        }

        return $supportingConfigs;
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
    protected function getAbsolutePath(string $path)
    {
        return $this->fileSystem->isAbsolutePath($path)
            ? $path
            : $this->rootPath . '/' . ltrim($path, '/');
    }
}
