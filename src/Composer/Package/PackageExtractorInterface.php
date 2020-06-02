<?php
/**
 * @file
 * PackageExtractorInterface.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Package;

use Composer\Installer\PackageEvent;
use Composer\Package\PackageInterface;

/**
 * Interface PackageExtractorInterface
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Composer\Package
 */
interface PackageExtractorInterface
{
    /**
     * Extracts packages from package events triggered by composer
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @return \Composer\Package\PackageInterface
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     *     If the operation in the event is unknown and the package can not be
     *     extracted
     */
    public function extractFromEvent(PackageEvent $event): PackageInterface;
}
