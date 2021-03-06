<?php
/**
 * @file
 * ComposerLinkerPluginCommandProvider.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Commands;

use Composer\Plugin\Capability\CommandProvider;
use JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageLocator;

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
        $packageLocator = new PackageLocator();

        return [
            new LinkCommand($packageLocator),
            new UnlinkCommand($packageLocator)
        ];
    }
}
