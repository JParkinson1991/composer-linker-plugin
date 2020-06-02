<?php
/**
 * @file
 * ComposerLinkerPluginCommandProviderTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Commands;

use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\ComposerLinkerPluginCommandProvider;
use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\LinkCommand;
use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\UnlinkCommand;
use JParkinson1991\ComposerLinkerPlugin\Tests\Support\ArrayTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Class ComposerLinkerPluginCommandProviderTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Commands
 */
class ComposerLinkerPluginCommandProviderTest extends TestCase
{
    /**
     * Leverage array testing helpers
     */
    use ArrayTestTrait;

    /**
     * Test that the command provider provides a link command
     *
     * @return void
     */
    public function testItProvidesALinkCommand(): void
    {
        $commandProvider = new ComposerLinkerPluginCommandProvider();

        $this->assertArrayContainsInstanceOf(LinkCommand::class, $commandProvider->getCommands());
    }

    /**
     * Tests that the command provider provides an unlink command
     *
     * @return void
     */
    public function testItProvidersAnUnlinkCommand(): void
    {
        $commandProvider = new ComposerLinkerPluginCommandProvider();

        $this->assertArrayContainsInstanceOf(UnlinkCommand::class, $commandProvider->getCommands());
    }
}
