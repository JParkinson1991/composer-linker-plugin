<?php
/**
 * @file
 * PackageLinkConfigFactory.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Config;

use JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException;

/**
 * Class PackageLinkConfigFactory
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Factory
 */
class LinkConfigFactory implements LinkConfigFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function create(string $packageName, $definition): LinkConfig
    {
        if (is_string($definition)) {
            return $this->createFromString($packageName, $definition);
        }

        throw InvalidConfigException::unexpectedConfigFormat();
    }

    /**
     * @param string $packageName
     * @param string $destinationDir
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Config\LinkConfig
     */
    public function createFromString(string $packageName, string $destinationDir): LinkConfig
    {
        return new LinkConfig($packageName, $destinationDir);
    }
}
