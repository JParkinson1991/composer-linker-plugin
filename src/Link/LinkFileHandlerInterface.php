<?php
/**
 * @file
 * LinkFileHandlerInterface.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Link;

/**
 * Interface LinkFileHandlerInterface
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Link
 */
interface LinkFileHandlerInterface
{
    /**
     * Links files for a package as configured using the passed link
     * definition.
     *
     * This method determines whether link definition is for the entire
     * package directory or not and routes to relevant sub method
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionInterface $linkDefinition
     *
     * @throws \Exception
     */
    public function link(LinkDefinitionInterface $linkDefinition): void;

    /**
     * Unlinks files for a package as configured using the passed link
     * definition
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionInterface $linkDefinition
     *
     * @throws \Exception
     */
    public function unlink(LinkDefinitionInterface $linkDefinition): void;
}
