<?php
/**
 * @file
 * InvalidConfigExceptionTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Exception;

use JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException;
use PHPUnit\Framework\TestCase;

/**
 * Class InvalidConfigExceptionTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Exception
 */
class InvalidConfigExceptionTest extends TestCase
{
    /**
     * Tests that the expected exception is returned when using the plugin
     * config not array factory method
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\InvalidConfigException
     */
    public function testItReturnsValidPluginConfigNotAnArray()
    {
        $exception = InvalidConfigException::pluginConfigNotAnArray();

        $this->assertStringContainsStringIgnoringCase('not an array', $exception->getMessage());
        $this->assertSame(InvalidConfigException::CODE_NOT_ARRAY, $exception->getCode());
    }

    /**
     * Tests than expected exception is returned when using the package name
     * not string factory method
     */
    public function testItReturnsValidPackageNameNotString()
    {
        $exception = InvalidConfigException::packageNameNotString(false);

        $this->assertStringContainsStringIgnoringCase('package name not a string', $exception->getMessage());
        $this->assertSame(InvalidConfigException::CODE_PACKAGE_NAME_NOT_STRING, $exception->getCode());
    }

    /**
     * Tests that the expected array is returned when using the unexpected
     * config format factory method
     *
     * Tests both default and with provided key parameter
     */
    public function testItReturnsValidUnexpectedConfigFormat()
    {
        $exception = InvalidConfigException::unexpectedConfigFormat();

        $this->assertStringContainsStringIgnoringCase('unexpected config format', $exception->getMessage());
        $this->assertSame(InvalidConfigException::CODE_UNEXPECTED_FORMAT, $exception->getCode());

        $exception = InvalidConfigException::unexpectedConfigFormat('test-key');
        $this->assertStringContainsString('test-key', $exception->getMessage());
    }
}
