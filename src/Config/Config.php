<?php
/**
 * @file
 * Config.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Config;

use Composer\Package\PackageInterface;

/**
 * Class Config
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Config
 */
final class Config implements ConfigInterface
{
    /**
     * An array of configuration for packages to be handled by this plugin.
     *
     * Each key of the array is a package name with its value being an array
     * containing the following key value pairs:
     * - dir (string)
     *       the name of the mapped directory
     * - files (array)
     *       the paths to specific files to map if not mapping the entire
     *       package directory. Each element of this array if a sub array
     *       with the following key values:
     *           - source
     *               The source file relative to the package root
     *           - dest
     *               The destination file relative to the mapped dir root
     * - options (array)
     *       an array of plugin options specific to this package
     *
     * @var array
     */
    protected $config = [];

    /**
     * Config constructor.
     *
     * Force instantiation via factory
     */
    private function __construct()
    {
    }

    /**
     * Checks if a package has mapping config associated with it
     *
     * @param PackageInterface $package
     *     The package to check
     *
     * @return bool
     */
    public function packageHasConfig(PackageInterface $package): bool
    {
        return array_key_exists($package->getPrettyName(), $this->config);
    }

    /**
     * Returns the linked directory for a package.
     *
     * @param PackageInterface $package
     *     The package to get the link dir for
     *
     * @return string|null
     */
    public function getPackageLinkDir(PackageInterface $package): ?string
    {
        return (isset($this->config[$package->getPrettyName()]['dir']))
            ? $this->config[$package->getPrettyName()]['dir']
            : null;
    }

    /**
     * Returns the specific files to link for a package.
     *
     * @param PackageInterface $package
     *     The package to get the linked files for
     *
     * @return string[]|null
     */
    public function getPackageLinkFiles(PackageInterface $package): ?array
    {
        return (isset($this->config[$package->getPrettyName()]['files']))
            ? $this->config[$package->getPrettyName()]['files']
            : null;
    }

    /**
     * Returns whether linking should occur via copy or symlink for a package.
     *
     * @param PackageInterface $package
     *     The package to check the copy config for
     *
     * @return bool
     */
    public function packageUsesCopy(PackageInterface $package): bool
    {
        return (isset($this->config[$package->getPrettyName()]['options']['copy']))
            ? (bool)$this->config[$package->getPrettyName()]['options']['copy']
            : false;
    }

    /**
     * Returns an instance of this class from a configuration array.
     *
     * @param array $configArray
     *     The configuration array.
     *     Usually this will come from the composer.json extra object.
     *
     * @return self
     */
    public static function fromArray(array $configArray): self
    {
        if (!array_key_exists('links', $configArray)) {
            throw new ConfigLoadException('No links key found in config');
        }

        // Instantiate the new object
        $configInstance = new self();

        // Process all of the mapping links
        foreach ($configArray['links'] as $packageName => $packageConfig) {
            // If config is a string, this is a simple mapping of a package
            // name to a directory, store the value and move on.
            if (is_string($packageConfig) === true) {
                $configInstance->config[$packageName]['dir'] = ltrim($packageConfig, './\\');
                $configInstance->config[$packageName]['files'] = [];
                $configInstance->config[$packageName]['options'] = (!empty($configArray['options']))
                    ? $configArray['options']
                    : [];

                continue;
            }

            // If package config is an array it required further processing for
            // specific files to map, specific options etc.
            if (is_array($packageConfig)) {
                // Ensure as a bare minimum that a complex package config
                // contains a 'dir' key with a string a value
                if (empty($packageConfig['dir']) || !is_string($packageConfig['dir'])) {
                    throw new ConfigLoadException(
                        'Invalid configuration options for '.$packageName
                        .' \'dir\' not set or is invalid'
                    );
                }

                // Config, deemed valid, store directory mapping
                $configInstance->config[$packageName]['dir'] = ltrim($packageConfig['dir'], './\\');

                // Handle any files if required
                if (!empty($packageConfig['files']) && is_array($packageConfig['files'])) {
                    $packageFiles = [];

                    // Loop the files removing any leading dots or directory separators
                    foreach ($packageConfig['files'] as $fileMapping) {
                        // For string entries, source path == dest path
                        // Simple map
                        if (is_string($fileMapping)) {
                            $filePath = ltrim($fileMapping, './\\');
                            $packageFiles[] = [
                                'source' => $filePath,
                                'dest' => $filePath
                            ];

                            continue;
                        }

                        // Array mapping, source and dest path both defined
                        // Ensure both are strings or abort
                        if (is_array($fileMapping) && sizeof($fileMapping) === 1){
                            // Get the array key, this is the raw source path
                            $key = array_keys($fileMapping)[0];

                            // Ensure both the raw source path and dest path
                            // are strings
                            if (!is_string($key) || !is_string($fileMapping[$key])) {
                                throw new ConfigLoadException(
                                    $packageName.' contains an invalid file entry. this'
                                );
                            }

                            $sourcePath = ltrim($key, './\\');
                            $destPath = ltrim($fileMapping[$key], './\\');

                            $packageFiles[] = [
                                'source' => $sourcePath,
                                'dest' => $destPath
                            ];

                            continue;
                        }

                        // Unhandled file mapping type
                        throw new ConfigLoadException(
                            $packageName.' contains an invalid file entry. that'
                        );
                    }

                    if (!empty($packageFiles)) {
                        $configInstance->config[$packageName]['files'] = $packageFiles;
                    }
                }

                // Handle options
                if (!empty($packageConfig['options']) && is_array($packageConfig['options'])) {
                    $configInstance->config[$packageName]['options'] = $packageConfig['options'];
                }
                else{
                    $configInstance->config[$packageName]['options'] = (!empty($configArray['options']))
                        ? $configArray['options']
                        : [];
                }

                continue;
            }

            // Unhandled configuration type
            throw new ConfigLoadException(
                'Invalid configuration options for '.$packageName
                .' expected string directory mapping or config array.'
            );
        }

        return $configInstance;
    }

}