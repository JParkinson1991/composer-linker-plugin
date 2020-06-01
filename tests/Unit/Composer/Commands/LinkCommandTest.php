<?php
/**
 * @file
 * LinkCommandTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Commands;

use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\AbstractPluginCommand;
use JParkinson1991\ComposerLinkerPlugin\Composer\Commands\LinkCommand;
use PHPUnit\Framework\TestCase;

/**
 * Class LinkCommandTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Composer\Commands
 */
class LinkCommandTest extends AbstractPluginCommandTest
{
    /**
     * Returns the instance to execute abstracted tests against
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Composer\Commands\AbstractPluginCommand
     */
    protected function getInstance(): AbstractPluginCommand
    {
        return new LinkCommand();
    }

    /**
     * Tests that the command will execute linking against a repository when
     * run without any arguments
     *
     * @return void
     */
    public function testItExecutesRepsoitoryLinkWhenRunWithoutArguments(): void
    {
        //tests
    }

    public function testItExecutesPackageLinkFromArguments(): void
    {
        // tests
    }
}
