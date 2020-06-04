<?php
/**
 * @file
 * LinkExecutor.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Link;

use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use Exception;
use JParkinson1991\ComposerLinkerPlugin\Exception\ConfigNotFoundException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorExceptionCollection;

/**
 * Class LinkExecutor
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Link
 */
class LinkExecutor implements LinkExecutorInterface
{
    /**
     * The local link definition factory
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactoryInterface
     */
    protected $linkDefinitionFactory;

    /**
     * The local link file handler
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandlerInterface
     */
    protected $linkFileHandler;

    /**
     * LinkExecutor constructor.
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactoryInterface $linkDefinitionFactory
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandlerInterface $linkFileHandler
     */
    public function __construct(
        LinkDefinitionFactoryInterface $linkDefinitionFactory,
        LinkFileHandlerInterface $linkFileHandler
    ) {
        $this->linkDefinitionFactory = $linkDefinitionFactory;
        $this->linkFileHandler = $linkFileHandler;
    }

    /**
     * Links a given package.
     *
     * Builds a link definition before passing it to the file handler
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException
     */
    public function linkPackage(PackageInterface $package): void
    {
        try {
            $linkDefinition = $this->linkDefinitionFactory->createForPackage($package);
            $this->linkFileHandler->link($linkDefinition);
        }
        catch (Exception $e) {
            throw new LinkExecutorException($package, $e);
        }
    }

    /**
     * Unlinks a given package.
     *
     * Builds a link definition before passing it to the file handler
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException
     */
    public function unlinkPackage(PackageInterface $package): void
    {
        try {
            $linkDefinition = $this->linkDefinitionFactory->createForPackage($package);
            $this->linkFileHandler->unlink($linkDefinition);
        }
        catch (Exception $e) {
            throw new LinkExecutorException($package, $e);
        }
    }

    /**
     * Links all relevant packages within a given repository.
     *
     * Config not found errors are ignored by this method, invalid configs
     * etc are still flagged.
     *
     * @param \Composer\Repository\RepositoryInterface $repository
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorExceptionCollection
     */
    public function linkRepository(RepositoryInterface $repository): void
    {
        $exceptionCollection = new LinkExecutorExceptionCollection();

        foreach ($repository->getPackages() as $package) {
            try {
                $this->linkPackage($package);
            }
            catch (LinkExecutorException $e) {
                // Config not found exceptions are not treat as an error
                // when linking all packages within a repository
                if (!$e->getExecutionException() instanceof ConfigNotFoundException) {
                    $exceptionCollection->addException($e);
                }
            }
        }

        if ($exceptionCollection->hasExceptions()) {
            throw $exceptionCollection;
        }
    }

    /**
     * Unlinks all relevant packages within a given repository.
     *
     * Config not found will not be treat as an error by this method,
     * invalid configs will still be treat as errors.
     *
     * @param \Composer\Repository\RepositoryInterface $repository
     *     The repository containing packages to unlink
     *
     * @return void
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorExceptionCollection
     */
    public function unlinkRepository(RepositoryInterface $repository): void
    {
        $exceptionCollection = new LinkExecutorExceptionCollection();

        foreach ($repository->getPackages() as $package) {
            try {
                $this->unlinkPackage($package);
            }
            catch (LinkExecutorException $e) {
                // config not found not treated an error when processing all
                // within a repository
                if (!$e->getExecutionException() instanceof ConfigNotFoundException) {
                    $exceptionCollection->addException($e);
                }
            }
        }

        if ($exceptionCollection->hasExceptions()) {
            throw $exceptionCollection;
        }
    }
}
