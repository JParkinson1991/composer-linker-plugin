<?php
/**
 * @file
 * PackageUtil.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Package;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Package\PackageInterface;
use Composer\Installer\PackageEvent;

/**
 * Class PackageUtil
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Package
 */
final class PackageUtil
{
    /**
     * Returns the package object for the received package event
     *
     * @param PackageEvent $event
     *     The package event to get the package object from
     *
     * @return PackageInterface
     *
     * @throws UnhandledPackageOperationException
     */
    public static function getPackageFromEvent(PackageEvent $event)
    {
        switch($operationClass = get_class($operation = $event->getOperation())){
            case InstallOperation::class:
            case UninstallOperation::class:
                /* @var InstallOperation $operation */
                return $operation->getPackage();
            case UpdateOperation::class:
                /* @var UpdateOperation $operation */
                return $operation->getTargetPackage();
            default:
                throw new UnhandledPackageOperationException(strtr(
                    'Unhandled operation. Expected: [:expected] Got: [:got]',
                    [
                        ':expected' => implode(', ', [
                            InstallOperation::class,
                            UninstallOperation::class,
                            UpdateOperation::class
                        ]),
                        ':got' => $operationClass
                    ]
                ));
        }
    }

    /**
     * Returns the install path of the package that is part of the provided
     * package event
     *
     * @param PackageEvent $event
     *     The package containing event
     *
     * @return string
     *     The package installation path
     *
     * @throws UnhandledPackageOperationException
     */
    public static function getPackageInstallPathFromEvent(PackageEvent $event): string
    {
        /* @var \Composer\Installer\InstallationManager $installerManager */
        $installerManager = $event->getComposer()->getInstallationManager();
        $package = self::getPackageFromEvent($event);

        return $installerManager->getInstallPath($package);
    }

}