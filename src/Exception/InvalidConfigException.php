<?php
/**
 * @file
 * InvalidConfigException.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Exception;

use Exception;

/**
 * Class InvalidConfigException
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Exception
 */
class InvalidConfigException extends Exception
{
    /**
     * Returns an instance of this exception in the context of a missing
     * data key where it expected
     *
     * @param string $key
     *     The missing key
     * @param string $at
     *     Where it was missing from
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public static function missingKey(string $key, string $at): self
    {
        return new self(sprintf(
            'Missing data with key: %s. At: %s',
            $key,
            $at
        ));
    }

    /**
     * Returns an instance of this exception when config contains an unexpected
     * type
     *
     * @param string $at
     *     Where the unexpected type was found
     * @param string $expected
     *     What was expected
     * @param mixed $gotValue
     *     The found value
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public static function unexpectedType(string $at, string $expected, $gotValue): self
    {
        return new self(sprintf(
            'Invalid config at %s. Expected: %s. Got: %s',
            $at,
            $expected,
            gettype($gotValue)
        ));
    }
}
