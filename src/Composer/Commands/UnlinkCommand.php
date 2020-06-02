<?php
/**
 * @file
 * UnlinkCommand.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Commands;

use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor;

/**
 * Class UnlinkCommand
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Composer\Commands
 */
class UnlinkCommand extends AbstractPluginCommand
{
    /**
     * Returns the name stub to automatically be applied to full command names
     * and aliases
     *
     * @return string
     */
    protected function nameStub(): string
    {
        return 'unlink';
    }

    /**
     * Returns the description of the command
     *
     * @return string
     */
    protected function description(): string
    {
        return 'Runs package unlinking as per plugin configuration';
    }

    /**
     * Runs the actual execution of a package against the link executor within
     * the context of the command.
     *
     * Exceptions caught and handled by the base command, they do not need to be
     * handled by extending commands.
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor $linkExecutor
     *     The link executor
     * @param \Composer\Package\PackageInterface $package
     *     The package to execute
     *
     * @return void
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException
     *
     */
    protected function doExecutePackage(LinkExecutor $linkExecutor, PackageInterface $package): void
    {
        $linkExecutor->unlinkPackage($package);
    }

    /**
     * Runs the actual execution of a repository against the link executor
     * within the context of the command.
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor $linkExecutor
     *     The link executor
     * @param \Composer\Repository\RepositoryInterface $repository
     *     The repository to execute
     *
     * @return void
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorExceptionCollection
     *
     */
    protected function doExecuteRepository(LinkExecutor $linkExecutor, RepositoryInterface $repository): void
    {
        $linkExecutor->unlinkRepository($repository);
    }
}
