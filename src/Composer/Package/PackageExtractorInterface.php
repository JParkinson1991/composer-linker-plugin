<?php
/**
 * @file
 * PackageExtractorInterface.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Package;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
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
     */
    public function extractFromEvent(PackageEvent $event): PackageInterface;
}