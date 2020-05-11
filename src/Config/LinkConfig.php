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
class LinkConfig implements LinkConfigInterface
{
    /**
     * The name of the package this config is associated with
     *
     * @var string
     */
    protected $packageName;

    /**
     * The destination directory of the associated package once linked
     *
     * @var string
     */
    protected $destinationDir;

    /**
     * PackageLinkConfig constructor.
     *
     * @param string $packageName
     * @param string $destinationDir
     */
    public function __construct(string $packageName, string $destinationDir)
    {
        $this->packageName = $packageName;
        $this->destinationDir = $destinationDir;
    }

    /**
     * @inheritDoc
     */
    public function getDestinationDir(): string
    {
        return $this->destinationDir;
    }

    /**
     * @inheritDoc
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
