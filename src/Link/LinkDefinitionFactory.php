<?php
/**
 * @file
 * LinkDefinitionFactory.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Link;

use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException;
use JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException;

/**
 * Class LinkDefinitionFactory
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Link
 */
class LinkDefinitionFactory implements LinkDefinitionFactoryInterface
{
    /**
     * Various plugin config array keys
     */
    public const CONFIG_KEY_ROOT = 'linker-plugin';
    public const CONFIG_KEY_LINKS = 'links';
    public const CONFIG_KEY_LINKS_DIR = 'dir';
    public const CONFIG_KEY_OPTIONS = 'options';
    public const CONFIG_KEY_OPTIONS_COPY = 'copy';

    /**
     * The root package that contains extra config data for link definitions
     *
     * @var \Composer\Package\RootPackageInterface
     */
    protected $rootPackage;

    /**
     * LinkDefinitionFactory constructor.
     *
     * @param \Composer\Package\RootPackageInterface $rootPackage
     */
    public function __construct(RootPackageInterface $rootPackage)
    {
        $this->rootPackage = $rootPackage;
    }

    /**
     * Creates a link definition object for the given package using the
     * associated config data found in the root package.
     *
     * @param \Composer\Package\PackageInterface $package
     *     The package the link definition.
     *     Composer extra data will be search to find a corresponding data
     *     set for this package and build the instance using that
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public function createForPackage(PackageInterface $package): LinkDefinition
    {
        // Find any configuration data for the package
        $extra = $this->rootPackage->getExtra();
        if (!isset($extra[self::CONFIG_KEY_ROOT][self::CONFIG_KEY_LINKS][$package->getName()])) {
            throw ConfigNotFoundException::forPackage($package);
        }

        $packageConfig = $extra[self::CONFIG_KEY_ROOT][self::CONFIG_KEY_LINKS][$package->getName()];
        switch (gettype($packageConfig)) {
            case 'string':
                return $this->createWithStringConfig($package, $packageConfig);
            case 'array':
                return $this->createWithArrayConfig($package, $packageConfig);
            default:
                throw InvalidConfigException::unexpectedType(
                    $package->getName(),
                    'string or array',
                    $packageConfig
                );
        }
    }

    /**
     * Creates a link definition instance from a simple string config.
     *
     * When finding a simple string config in the root package extra data it
     * is treat as destination directory.
     *
     * This method will use the config value as destination directory and
     * apply any global options to the created instance by calling the
     * internal apply options method on this instance with no dedicated
     * values.
     *
     * @param \Composer\Package\PackageInterface $package
     *     The package to create the link definition instance for
     * @param string $packageConfig
     *     The string package config, treat as a destination directory
     *
     * @return array|\JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    protected function createWithStringConfig(PackageInterface $package, string $packageConfig): LinkDefinition
    {
        $linkDefinition = new LinkDefinition($package, $packageConfig);
        $linkDefinition = $this->applyOptions($linkDefinition);

        return $linkDefinition;
    }

    /**
     * Creates a link definition instance from a complex array package config
     * found in the compose extra data.
     *
     * This method will parse the complex array and extra data to instantiate
     * the link definition instance and configure it as required.
     *
     * @param \Composer\Package\PackageInterface $package
     *     The package to create the link definition instance for
     * @param array $packageConfig
     *     The complex array config
     *
     * @return array|\JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    protected function createWithArrayConfig(PackageInterface $package, array $packageConfig)
    {
        // Ensure destination directory set and valid
        if (!array_key_exists(self::CONFIG_KEY_LINKS_DIR, $packageConfig)) {
            throw InvalidConfigException::missingKey(
                self::CONFIG_KEY_LINKS_DIR,
                $package->getName()
            );
        }
        if (!is_string($packageConfig[self::CONFIG_KEY_LINKS_DIR])) {
            throw InvalidConfigException::unexpectedType(
                $package->getName().' - '.self::CONFIG_KEY_LINKS_DIR,
                'string',
                $packageConfig[self::CONFIG_KEY_LINKS_DIR]
            );
        }

        // Instantiate the object using package and the links dir
        $linkDefinition = new LinkDefinition($package, $packageConfig[self::CONFIG_KEY_LINKS_DIR]);

        // Apply options to the link definition passing any defined options
        // from the complex config array. If they are found they are passed
        // and they will take priority over global options applied by the
        // applyOptions method
        $linkDefinition = $this->applyOptions(
            $linkDefinition,
            (!empty($packageConfig[self::CONFIG_KEY_OPTIONS]) && is_array($packageConfig[self::CONFIG_KEY_OPTIONS]))
                ? $packageConfig[self::CONFIG_KEY_OPTIONS]
                : []
        );

        return $linkDefinition;
    }

    /**
     * Applies options to a given link definition.
     *
     * This method enables both global and at package level option overrides
     * to applied to a link definition.
     *
     * Merging global options and provided config options (provided options
     * take priority) all relevant and valid config options are applied to
     * the passed instance
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition $linkDefinition
     *     The link definition to apply the options to
     * @param array $configOptions
     *     The options in array format to apply
     *
     * @return array|\JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    protected function applyOptions(LinkDefinition $linkDefinition, array $configOptions = []): LinkDefinition
    {
        $extra = $this->rootPackage->getExtra();

        // If global options defined, validate them before merging in provided options
        // Provided options take priority over global options in merge
        if (
            array_key_exists(self::CONFIG_KEY_ROOT, $extra)
            && array_key_exists(self::CONFIG_KEY_OPTIONS, $extra[self::CONFIG_KEY_ROOT])
        ) {
            if (!is_array($extra[self::CONFIG_KEY_ROOT][self::CONFIG_KEY_OPTIONS])) {
                throw InvalidConfigException::unexpectedType(
                    self::CONFIG_KEY_ROOT.'.'.self::CONFIG_KEY_OPTIONS,
                    'array',
                    $extra[self::CONFIG_KEY_ROOT][self::CONFIG_KEY_OPTIONS]
                );
            }

            $configOptions = array_merge($extra[self::CONFIG_KEY_ROOT][self::CONFIG_KEY_OPTIONS], $configOptions);
        }

        // If copy option found
        if (isset($configOptions[self::CONFIG_KEY_OPTIONS_COPY])) {
            // Validate type
            if (!is_bool($configOptions[self::CONFIG_KEY_OPTIONS_COPY])) {
                throw InvalidConfigException::unexpectedType(
                    $linkDefinition->getPackage()->getName().' - '.self::CONFIG_KEY_OPTIONS_COPY,
                    'bool',
                    $configOptions[self::CONFIG_KEY_OPTIONS_COPY]
                );
            }

            $linkDefinition->setCopyFiles($configOptions[self::CONFIG_KEY_OPTIONS_COPY]);
        }

        return $linkDefinition;
    }
}
