<?php
/**
 * @file
 * ComposerLinkerPluginCommandProvider.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Commands;

use Composer\Plugin\Capability\CommandProvider;

/**
 * Class ComposerLinkerPluginCommandProvider
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Composer\Commands
 */
class ComposerLinkerPluginCommandProvider implements CommandProvider
{
    /**
     * @inheritDoc
     */
    public function getCommands(): array
    {
        return [
            new LinkCommand(),
            new UnlinkCommand()
        ];
    }
}
