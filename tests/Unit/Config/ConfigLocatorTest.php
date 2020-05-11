<?php
/**
 * @file
 * ConfigLocatorTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Config;

use Composer\Package\RootPackageInterface;
use JParkinson1991\ComposerLinkerPlugin\Composer\Plugin\ComposerLinkerPlugin;
use JParkinson1991\ComposerLinkerPlugin\Config\LinkConfigLocator;
use JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigLocatorTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Config
 */
class ConfigLocatorTest extends TestCase
{
    /**
     * Tests config is found and returned correctly
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     */
    public function testItFindsConfigInRootPackage()
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')
            ->willReturn([
                ComposerLinkerPlugin::PLUGIN_CONFIG_KEY => 'found'
            ]);

        $configLocator = new LinkConfigLocator();
        $locatedConfig = $configLocator->locateInRootPackage(
            $rootPackage,
            ComposerLinkerPlugin::PLUGIN_CONFIG_KEY
        );

        $this->assertSame('found', $locatedConfig);
    }

    /**
     * Tests the config not found exception is thrown as expected
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException
     */
    public function testItThrowsAnExceptionWhenConfigNotFound()
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')
            ->willReturn([]);

        $configLocator = new LinkConfigLocator();

        $this->expectException(ConfigNotFoundException::class);
        $configLocator->locateInRootPackage($rootPackage, 'key');
    }
}
