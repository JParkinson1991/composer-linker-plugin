<?php
/**
 * @file
 * PackageLinkConfigArrayDefinitionException.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Exception;

/**
 * Class PackageLinkConfigArrayDefinitionException
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Exception
 */
class PackageLinkConfigInvalidArrayDefinitionException extends \InvalidArgumentException
{
    public const CODE_MISSING_KEY_DATA = 0;
    public const CODE_INVALID_DATA_TYPE = 1;

    /**
     * Return exception in state of missing required definition key
     *
     * @param string $key
     *     The key missing from the definition
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Exception\PackageLinkConfigInvalidArrayDefinitionException
     */
    public static function missingKeyData(string $key)
    {
        return new self(
            'Missing required data under key: ' . $key,
            self::CODE_MISSING_KEY_DATA
        );
    }

    /**
     * Return exception caused by actual value type of key not matching the
     * expected
     *
     * @param string $key
     *     The key holding te value
     * @param string $expectedType
     *     The expected value data type
     * @param mixed $actualValue
     *     The value with incorrect type
     */
    public static function invalidDataType(string $key, string $expectedType, $actualValue)
    {
        return new self(
            sprintf(
                "Invalid value data type for %s. Expected %s. Got %s",
                $key,
                $expectedType,
                (is_object($actualValue))
                    ? get_class($actualValue)
                    : gettype($actualValue)
            ),
            self::CODE_INVALID_DATA_TYPE
        );
    }

}
