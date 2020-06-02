<?php
/**
 * @file
 * PackageExtractor.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Package;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\Package\PackageInterface;

/**
 * Class PackageExtractor
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Composer\Package
 */
class PackageExtractor
{
    /**
     * Extracts packages from package events triggered by composer
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @return \Composer\Package\PackageInterface
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Composer\Package\PackageExtractionUnhandledEventOperationException
     */
    public function extractFromEvent(PackageEvent $event): PackageInterface
    {
        $operation = $event->getOperation();

        if ($operation instanceof InstallOperation || $operation instanceof UninstallOperation) {
            return $operation->getPackage();
        }

        if ($operation instanceof UpdateOperation) {
            return $operation->getTargetPackage();
        }

        // throw extraction exceptions for unhandled operations
        throw new PackageExtractionUnhandledEventOperationException(
            'Failed to extract package from event. Trigger for operation: '.get_class($operation)
        );
    }
}
