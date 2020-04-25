<?php
/**
 * @file
 * PackageLinkConfigFactory.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Factory;

use JParkinson1991\ComposerLinkerPlugin\Config\PackageLinkConfig;
use JParkinson1991\ComposerLinkerPlugin\Exception\PackageLinkConfigInvalidArrayDefinitionException;

/**
 * Class PackageLinkConfigFactory
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Config
 */
final class PackageLinkConfigFactory
{
    /**
     * The name of the key containing the directory value
     */
    public const KEY_DIR = 'dir';

    /**
     * Creates a package config instance from an array definition
     *
     * @param string $supportedPackageName
     *     The name of the package supported by the created config instance
     * @param array $definition
     *     The package config definition
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Config\PackageLinkConfig
     */
    public function createFromArray(string $supportedPackageName, array $definition): PackageLinkConfig
    {
        // Validate required fields
        if (array_key_exists(self::KEY_DIR, $definition) === false) {
            throw PackageLinkConfigInvalidArrayDefinitionException::missingKeyData(self::KEY_DIR);
        }

        // Validate directory is string
        if (is_string($definition[self::KEY_DIR]) === false) {
            throw PackageLinkConfigInvalidArrayDefinitionException::invalidDataType(
                self::KEY_DIR,
                'string',
                $definition[self::KEY_DIR]
            );
        }

        $package = new PackageLinkConfig($supportedPackageName, $definition[self::KEY_DIR]);

        return $package;
    }
}
