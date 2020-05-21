<?php
/**
 * @file
 * LinkDefinitionFactory.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Link;

use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Exception;
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
    public const CONFIG_KEY_LINKS_FILES = 'files';
    public const CONFIG_KEY_OPTIONS = 'options';
    public const CONFIG_KEY_OPTIONS_COPY = 'copy';
    public const CONFIG_KEY_OPTIONS_DELETEORPHANS = 'delete-orphans';

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
     * @return \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    protected function createWithArrayConfig(PackageInterface $package, array $packageConfig): LinkDefinition
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

        // Handle apply options for the package
        // Initialise with assumed no option overrides
        // Find and validate options as needed
        // If valid overrides found set for use in options application method
        // Apply options, applies all global options if found overridden by
        // what found at package level
        $packageConfigOptionOverrides = [];
        if (array_key_exists(self::CONFIG_KEY_OPTIONS, $packageConfig)) {
            // Validate type
            if (!is_array($packageConfig[self::CONFIG_KEY_OPTIONS])) {
                throw InvalidConfigException::unexpectedType(
                    $package->getName().' - '.self::CONFIG_KEY_OPTIONS,
                    'array',
                    $packageConfig[self::CONFIG_KEY_OPTIONS]
                );
            }

            // Validate, set overrides
            $packageConfigOptionOverrides = $packageConfig[self::CONFIG_KEY_OPTIONS];
        }
        $this->applyOptions($linkDefinition, $packageConfigOptionOverrides);

        // Process specific file mappings if needed
        if (array_key_exists(self::CONFIG_KEY_LINKS_FILES, $packageConfig)) {
            // Validate type
            if (!is_array($packageConfig[self::CONFIG_KEY_LINKS_FILES])) {
                throw InvalidConfigException::unexpectedType(
                    $package->getName().' - '.self::CONFIG_KEY_LINKS_FILES,
                    'array',
                    $packageConfig[self::CONFIG_KEY_LINKS_FILES]
                );
            }

            $linkDefinition = $this->applyFileMappings($linkDefinition, $packageConfig[self::CONFIG_KEY_LINKS_FILES]);
        }

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
     *     The options in array format to override any global options with
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

        // If delete orphans option found
        if (isset($configOptions[self::CONFIG_KEY_OPTIONS_DELETEORPHANS])) {
            // Validate type
            if (!is_bool($configOptions[self::CONFIG_KEY_OPTIONS_DELETEORPHANS])) {
                throw InvalidConfigException::unexpectedType(
                    $linkDefinition->getPackage()->getName().' - '.self::CONFIG_KEY_OPTIONS_DELETEORPHANS,
                    'bool',
                    $configOptions[self::CONFIG_KEY_OPTIONS_DELETEORPHANS]
                );
            }

            $linkDefinition->setDeleteOrphanDirs($configOptions[self::CONFIG_KEY_OPTIONS_DELETEORPHANS]);
        }

        return $linkDefinition;
    }

    /**
     * Handles application of file mappings for a link definition using the
     * raw file mappings config pulled from the root package.
     *
     * The main source of root package config is the composer.json 'extra'
     * definition. Due to this config being defined in json there are many
     * ways in which a file mapping can be defined which can be handled by
     * this method. If a mapping is defined in a way which can be handled it
     * is passed off to the relevant application method, if the mapping can
     * not be handled an exception is thrown.
     *
     * The following mapping structures can be handled by this class and the
     * related application methods:
     *     - Flat strings (same source => destination)
     *         > integer index, string value
     *         Value is treat as both the source and destination
     *     - Flat array
     *         > integer index, array value
     *         Value is treat as array that can contain:
     *             Source => Destination Pair
     *             Source => Multiple Destinations
     *     - Source => Destination pair
     *         > string index, string value
     *         Index treat as source, value treat as destination
     *     - Source => Multiple Destinations
     *         > string index, string[] value
     *         Index treat as source, value treat as multiple destinations for
     *         that source.
     *
     * @see applyFileMappingsSourceDestPair()
     * @see applyFileMappingsSourceMultipleDest()
     * @seeapplyFileMappingsFlatArray()
     * @see applyFileMappingsSameSourceDest()
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition $linkDefinition
     *     The link definition to add the file mappings to
     * @param array $fileMappings
     *     The array containing the raw file mappings
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     *
     * @noinspection NotOptimalIfConditionsInspection
     */
    protected function applyFileMappings(LinkDefinition $linkDefinition, array $fileMappings): LinkDefinition
    {
        // Ensure not empty
        if (empty($fileMappings)) {
            throw InvalidConfigException::unexpectedType(
                $linkDefinition->getPackage()->getName().' - '.self::CONFIG_KEY_LINKS_FILES,
                'non empty array',
                $fileMappings
            );
        }

        // Foreach mapping key => pair within the raw file mappings
        // Determine it's type of mapping and route to the relevant application
        // method. None handled types throw an exception
        foreach ($fileMappings as $source => $destination) {
            try {
                // Flat string mapping
                if (is_int($source) && is_string($destination)) {
                    $this->applyFileMappingsSameSourceDest($linkDefinition, $destination);
                }
                elseif (is_int($source) && is_array($destination)) {
                    $this->applyFileMappingsFlatArray($linkDefinition, $source, $destination);
                }
                elseif (is_string($source) && is_string($destination)) {
                    $this->applyFileMappingsSourceDestPair($linkDefinition, $source, $destination);
                }
                elseif (is_string($source) && is_array($destination)) {
                    $this->applyFileMappingsSourceMultipleDest($linkDefinition, $source, $destination);
                }
                else {
                    throw InvalidConfigException::unexpectedType(
                        $linkDefinition->getPackage()->getName().' - '.self::CONFIG_KEY_LINKS_FILES.' - '.$source,
                        'flat string, array mapping, source => dest mapping, source => dest array mapping',
                        $destination
                    );
                }
            }
            catch (Exception $e) {
                // Throw invalid config exceptions as is
                if ($e instanceof InvalidConfigException) {
                    throw $e;
                }

                // Wrap and throw anything else
                throw new InvalidConfigException(
                    sprintf(
                        "Failed to process file mappings at: %s. Got error %s",
                        $linkDefinition->getPackage()->getName().' - '.self::CONFIG_KEY_LINKS_FILES.' - '.$source,
                        $e->getMessage()
                    ),
                    $e->getCode(),
                    $e
                );
            }
        }

        return $linkDefinition;
    }

    /**
     * Handles application of the same source and destination mapping type.
     *
     * Routed to from the applyFileMappings method.
     *
     * Applies the given string value as both the source and destination to
     * link definition instance.
     *
     * @see applyFileMappings()
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition $linkDefinition
     *     The link definition to apply the file mapping to
     * @param string $sourceDest
     *     The value to use as both the source and destination in the
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition
     */
    protected function applyFileMappingsSameSourceDest(
        LinkDefinition $linkDefinition,
        string $sourceDest
    ): LinkDefinition {
        $linkDefinition->addFileMapping($sourceDest, $sourceDest);

        return $linkDefinition;
    }

    /**
     * Handles application of flat array mapping type.
     *
     * This method does not apply an file mappings, rather it determines
     * the nested mapping type for each element of the flat array and
     * routing off to the relevant application method.
     *
     * @see applyFileMappingsSourceDestPair
     * @see applyFileMappingsSourceMultipleDest
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition $linkDefinition
     *     The link definition to apply the file mappings to.
     * @param int $index
     *     The index/key this flat mapping was found it within the parent
     *     mappings, only purpose is for use in exception messages.
     * @param array $mappings
     *     Array where each element is either
     *     Source => Dest Pair
     *     Source => Multiple Dest Array
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    protected function applyFileMappingsFlatArray(
        LinkDefinition $linkDefinition,
        int $index,
        array $mappings
    ): LinkDefinition {
        if (empty($mappings)) {
            throw InvalidConfigException::unexpectedType(
                $linkDefinition->getPackage()->getName().' - '.self::CONFIG_KEY_LINKS_FILES.' - '.$index,
                'non empty array',
                $index
            );
        }

        foreach ($mappings as $source => $destination) {
            // Every nested flat array must provide a source key
            if (!is_string($source)) {
                throw InvalidConfigException::unexpectedType(
                    $linkDefinition->getPackage()->getName().' - '.self::CONFIG_KEY_LINKS_FILES.' - '.$index,
                    'string source value',
                    $source
                );
            }

            // If source => dest pair
            // Else if source => multiple destinations
            // else exception
            if (is_string($destination)) {
                $this->applyFileMappingsSourceDestPair($linkDefinition, $source, $destination);
            }
            elseif (is_array($destination)) {
                $this->applyFileMappingsSourceMultipleDest($linkDefinition, $source, $destination);
            }
            else {
                throw InvalidConfigException::unexpectedType(
                // phpcs:ignore
                    $linkDefinition->getPackage()->getName().' - '.self::CONFIG_KEY_LINKS_FILES.' - '.$index.' - '.$source,
                    'string or string[] destinations',
                    $destination
                );
            }
        }

        return $linkDefinition;
    }

    /**
     * Handles application of the source destination pair mapping type.
     *
     * Routed to from the applyFileMappings method.
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition $linkDefinition
     *     The link definition to apply the file mapping to
     * @param string $source
     *     The source of the file mapping
     * @param string $dest
     *     The destination of the file mapping
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition
     */
    protected function applyFileMappingsSourceDestPair(
        LinkDefinition $linkDefinition,
        string $source,
        string $dest
    ): LinkDefinition {
        $linkDefinition->addFileMapping($source, $dest);

        return $linkDefinition;
    }

    /**
     * Handles application of source to multiple destinations mapping types.
     *
     * Routed to from the applyFileMappings method.
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition $linkDefinition
     *     The link definition to apply the mappings to
     * @param string $source
     *     The source file
     * @param array $destinations
     *     An array of destinations.
     *     Expects a non associative (ie no string keys) array.
     *     Example ['dest1.txt', 'dest2.txt', 'dest3.txt']
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    protected function applyFileMappingsSourceMultipleDest(
        LinkDefinition $linkDefinition,
        string $source,
        array $destinations
    ): LinkDefinition {
        // Ensure not empty
        if (empty($destinations)) {
            throw InvalidConfigException::unexpectedType(
                $linkDefinition->getPackage()->getName().' - '.self::CONFIG_KEY_LINKS_FILES.' - '.$source,
                'non empty string array',
                $destinations
            );
        }

        // Process each dest file, validate, and set it
        foreach ($destinations as $i => $destFile) {
            if (!is_int($i)) {
                throw InvalidConfigException::unexpectedType(
                    $linkDefinition->getPackage()->getName().' - '.self::CONFIG_KEY_LINKS_FILES.' - '.$source,
                    'flat array',
                    $destFile
                );
            }

            if (!is_string($destFile)) {
                throw InvalidConfigException::unexpectedType(
                    $linkDefinition->getPackage()->getName().' - '.self::CONFIG_KEY_LINKS_FILES.' - '.$source.' - '.$i,
                    'string destination',
                    $destFile
                );
            }

            $linkDefinition->addFileMapping($source, $destFile);
        }

        return $linkDefinition;
    }
}
