<?php
/**
 * @file
 * LinkExecutor.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Link;

use Composer\Package\PackageInterface;

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
     * @throws \Exception
     */
    public function linkPackage(PackageInterface $package): void
    {
        $linkDefinition = $this->linkDefinitionFactory->createForPackage($package);
        $this->linkFileHandler->link($linkDefinition);
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
     * @throws \Exception
     */
    public function unlinkPackage(PackageInterface $package): void
    {
        $linkDefinition = $this->linkDefinitionFactory->createForPackage($package);
        $this->linkFileHandler->unlink($linkDefinition);
    }
}
