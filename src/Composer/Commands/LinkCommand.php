<?php
/**
 * @file
 * LinkCommand.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Commands;

use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor;

/**
 * Class LinkCommand
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Composer\Commands
 */
class LinkCommand extends AbstractPluginCommand
{
    /**
     * Returns the name stub to automatically be applied to full command names
     * and aliases
     *
     * @return string
     */
    protected function nameStub(): string
    {
        return 'link';
    }

    /**
     * Returns the description of the command
     *
     * @return string
     */
    protected function description(): string
    {
        return 'Runs package linking as per plugin configuration';
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
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException
     *
     * @return void
     */
    protected function doExecutePackage(LinkExecutor $linkExecutor, PackageInterface $package): void
    {
        $linkExecutor->linkPackage($package);
    }

    /**
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor $linkExecutor
     * @param \Composer\Repository\RepositoryInterface $repository
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorExceptionCollection
     */
    protected function doExecuteRepository(LinkExecutor $linkExecutor, RepositoryInterface $repository): void
    {
        $linkExecutor->linkRepository($repository);
    }
}
