<?php
/**
 * @file
 * PackageLinkConfigFactoryInterface.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Config;

/**
 * Interface PackageLinkConfigFactoryInterface
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Config
 */
interface LinkConfigFactoryInterface
{
    /**
     * Creates a package link config instance from a definition.
     *
     * @param string $packageName
     *     The name of the package to associate this config to
     * @param string|array $definition
     *     A string representing a destination directory for the link
     *     Or a complex link array structure
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Config\LinkConfig
     */
    public function create(string $packageName, $definition): LinkConfig;
}
