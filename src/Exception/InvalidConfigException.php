<?php
/**
 * @file
 * InvalidConfigException.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Exception;

/**
 * Class InvalidConfigException
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Exception
 */
class InvalidConfigException extends \Exception
{
    public const CODE_NOT_ARRAY = 1;
    public const CODE_PACKAGE_NAME_NOT_STRING = 2;
    public const CODE_UNEXPECTED_FORMAT = 3;

    /**
     * Throws an instance of this exception when parsed plugin definition is
     * not an array
     *
     * @return self
     */
    public static function pluginConfigNotAnArray(): self
    {
        return new self(
            'Plugin configuration is not an array',
            self::CODE_NOT_ARRAY
        );
    }

    /**
     * Throws an instance of this exception when receiving a package name and
     * it is not a string
     *
     * @param mixed $got
     *     The value of the key that was received
     *
     * @return self
     */
    public static function packageNameNotString($got): self
    {
        return new self(
            sprintf(
                'Package name not a string. Got: %s [%s]',
                $got,
                gettype($got)
            ),
            self::CODE_PACKAGE_NAME_NOT_STRING
        );
    }

    /**
     * Throws an instance of this exception when unexpected config formats
     * and encountered.
     *
     * @param string|int|null $atKey
     *     The unexpected config format identifier
     *
     * @return self
     */
    public static function unexpectedConfigFormat($atKey = null): self
    {
        return new self(
            sprintf(
                'Unexpected config format%s. Expected string or array',
                ($atKey !== null)
                    ? " at '".$atKey."'"
                    : ''
            ),
            self::CODE_UNEXPECTED_FORMAT
        );
    }
}
