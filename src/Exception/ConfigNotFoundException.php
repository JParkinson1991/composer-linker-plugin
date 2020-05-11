<?php
/**
 * @file
 * ConfigNotFoundException.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Exception;

/**
 * Class ConfigNotFoundException
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Exception
 */
class ConfigNotFoundException extends \Exception
{
    /**
     * Throws an instance of itself when configuration not found at an
     * expected key
     *
     * @param string $key
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     */
    public static function atKey(string $key): self
    {
        return new self("Failed to find config at key: ".$key);
    }
}
