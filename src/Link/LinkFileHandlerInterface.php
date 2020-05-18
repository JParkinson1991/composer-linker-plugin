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
    public function link(LinkDefinition $linkDefinition): void;

    public function unlink(LinkDefinition $linkDefinition): void;
}
