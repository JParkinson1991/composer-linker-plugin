<?php
/**
 * @file
 * PackageLinkConfig.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Config;

use Composer\Package\PackageInterface;

/**
 * Class PackageLinkConfig
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Config
 */
class PackageLinkConfig implements PackageLinkConfigInterface
{
    /**
     * The name of the package this config is associated with
     *
     * @var string
     */
    protected $packageName;

    /**
     * The destination directory for the package
     *
     * @var string
     */
    protected $destinationDirectory;

    /**
     * PackageLinkConfig constructor.
     *
     * @param string $packageName
     * @param string $destinationDirectory
     */
    public function __construct(string $packageName, string $destinationDirectory)
    {
        $this->packageName = $packageName;
        $this->destinationDirectory = $destinationDirectory;
    }

    /**
     * Package support determinator
     *
     * @param \Composer\Package\PackageInterface $package
     *     The package in question.
     *     Does this config work with this package?
     *
     *
     * @return bool
     */
    public function supports(PackageInterface $package): bool
    {
        return in_array(
            $this->packageName,
            [
                $package->getName(),
                $package->getPrettyName(),
                $package->getUniqueName()
            ],
            true
        );
    }

}
