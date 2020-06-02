<?php
/**
 * @file
 * BaseComposerTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Integration\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException as RuntimeExceptionAlias;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class BaseComposerTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Integration
 */
class BaseComposerTestCase extends TestCase
{
    /**
     * The mocked composer instance
     *
     * @var \Composer\Composer|\PHPUnit\Framework\MockObject\MockObject
     */
    private $composer;

    /**
     * The test project root
     *
     * Destroyed after every test case
     *
     * @var string
     */
    private $dirProjectRoot;

    /**
     * The path to the vendor directory
     *
     * All vendor files wil sit under this dir, this director sits under the
     * $dirProjectRoot
     *
     * @var string
     */
    private $dirVendor;

    /**
     * Contains an array of package names mapped to install directories
     *
     * Used in the installation manager mock, to return test package dirs and
     * populated in initialisePackage
     *
     * @see initialisePackage()
     *
     * @var array<string, string>
     *     key => package name
     *     value => absolute package install path
     */
    private $packageInstallPaths = [];

    /**
     * Contains an array of package instances
     *
     * Used in the local repository/repository manager mock. Populated
     * during in initialise package
     *
     * @see initialisePackage()
     *
     * @var PackageInterface[]
     */
    private $packageInstances = [];

    /**
     * The plugin configuration array, including it's parent root key
     *
     * Injected as the contents of the composer project's root package
     * 'extra' array.
     *
     * @see setUp
     *
     * @var array
     */
    private $pluginConfig = [];

    /**
     * Sets up this class prior to execution of a test case
     *
     * @return void
     */
    public function setUp(): void
    {
        // Determine project root directory
        $this->dirProjectRoot = (new \Composer\Util\Filesystem())->normalizePath(
            __DIR__.'../../../../var/composer_plugin_test_root'
        );

        // Determine vendor dir
        $this->dirVendor = $this->dirProjectRoot.'/vendor';

        // Left over project root path, delete it
        if (file_exists($this->dirProjectRoot)) {
            $this->tearDown();
        }

        // Create each directory
        $fileSystem = new Filesystem();
        $fileSystem->mkdir([
            $this->dirProjectRoot,
            $this->dirVendor
        ]);

        // Prepare the mocks, start with the config class, have it return
        // the vendor directory defined for the tests
        $config = $this->createMock(Config::class);
        $config
            ->method('get')
            ->with('vendor-dir')
            ->willReturn($this->dirVendor);

        // Create an installation manager that can be used to identify and
        // return tested package mocks
        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager
            ->method('getInstallPath')
            ->willReturnCallback(function (PackageInterface $package) {
                if (!array_key_exists($package->getName(), $this->packageInstallPaths)) {
                    throw new RuntimeExceptionAlias(sprintf(
                        'Internal error, unhandled package. Got: %s. Defined: %s',
                        $package->getName(),
                        implode(', ', array_keys($this->packageInstallPaths))
                    ));
                }

                return $this->packageInstallPaths[$package->getName()];
            });

        // Prepare a local repository
        $localRepository = $this->createMock(RepositoryInterface::class);
        $localRepository
            ->method('getPackages')
            ->willReturnCallback(function () {
                // Do in callback so current values always used
                // Do not need to do this with object vars due to how php internals handle
                // Non objects var do not remain current by reference however
                return $this->packageInstances;
            });
        $localRepository
            ->method('findPackages')
            ->willReturnCallback(function ($packageName) {
                $packages = [];
                foreach ($this->packageInstances as $package) {
                    if (strpos($package->getName(), $packageName) !== false) {
                        $packages[] = $package;
                    }
                }
                return $packages;
            });

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->method('getLocalRepository')
            ->willReturn($localRepository);

        // Prepare a root package that will return the plugin config
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage
            ->method('getExtra')
            ->willReturnCallback(function () {
                // Do in callback so current values always used
                return $this->pluginConfig;
            });

        // Prepare a composer instance
        // Have it return all it's mocked nested services
        $composer = $this->createMock(Composer::class);
        $composer
            ->method('getConfig')
            ->willReturn($config);
        $composer
            ->method('getInstallationManager')
            ->willReturn($installationManager);
        $composer
            ->method('getPackage')
            ->willReturn($rootPackage);
        $composer
            ->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        // Store composer for access
        $this->composer = $composer;
    }

    /**
     * Tears down the environment after running a test case
     *
     * @return void
     */
    public function tearDown(): void
    {
        (new FileSystem())->remove($this->dirProjectRoot);
    }

    /**
     * Assertation wrapper allowing file stubs to be used in file existence
     * checks.
     *
     * Stubs will be resolved using internal absolute path resolution.
     *
     * @see getAbsolutePath()
     *
     * @param string $filePathStub
     * @param string|null $resolvedFrom
     *
     * @return void
     */
    protected function assertFileStubExists(string $filePathStub, string $resolvedFrom = null): void
    {
        $this->assertFileExists($this->getAbsolutePath($filePathStub, $resolvedFrom));
    }

    /**
     * Assertation wrapper allowing file stubs to be used in file not existing
     * checks.
     *
     * Stubs will be resolved using internal absolute path resolution.
     *
     * @see getAbsolutePath()
     *
     * @param string $filePathStub
     * @param string|null $resolvedFrom
     *
     * @return void
     */
    protected function assertFileStubDoesNotExist(string $filePathStub, string $resolvedFrom = null): void
    {
        $this->assertFileDoesNotExist($this->getAbsolutePath($filePathStub, $resolvedFrom));
    }

    /**
     * Creates files from stubs
     *
     * @param array $filePathStubs
     *     The stubs
     * @param string|null $resolvedForm
     *     Where to resolve stubs from
     *
     * @return void
     */
    protected function createFiles(array $filePathStubs, string $resolvedForm = null): void
    {
        $fileSystem = new Filesystem();
        foreach ($filePathStubs as $stub) {
            $filePath = $this->getAbsolutePath($stub, $resolvedForm);
            $fileDir = dirname($filePath);

            if ($fileSystem->exists($fileDir) === false) {
                $fileSystem->mkdir($fileDir);
            }

            $fileSystem->touch($filePath);
        }
    }

    /**
     * Returns the absolute representation of a link stub
     *
     * @param string $pathStub
     * @param string $resolvedFrom
     *     Defaults to project root
     *
     * @return string
     */
    protected function getAbsolutePath(string $pathStub, string $resolvedFrom = null): string
    {
        $resolvedFrom = $resolvedFrom ?? $this->dirProjectRoot;

        return rtrim($resolvedFrom, '/').'/'.ltrim($pathStub, './');
    }

    /**
     * Returns an absolute path from the vendor dir
     *
     * @param string $pathStub
     *
     * @return string
     */
    protected function getAbsolutePathVendor(string $pathStub): string
    {
        return $this->getAbsolutePath($pathStub, $this->dirVendor);
    }

    /**
     * Returns the mock composer instance
     *
     * @return \Composer\Composer|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getComposer(): MockObject
    {
        return $this->composer;
    }

    /**
     * Initialise a package into the testable/mocked composer environment
     *
     * @param string $packageName
     *     The name of the package
     * @param string $installPath
     *     The install path of the package
     *     Provide this as relative from the vendor directory, do not use the
     *     full path
     * @param string[] $files
     *     A flat array of files to create relative to the $installDir
     *
     * @return PackageInterface
     */
    protected function initialisePackage(string $packageName, string $installPath, array $files = []): PackageInterface
    {
        // Ensure not previously added
        if (array_key_exists($packageName, $this->packageInstances)) {
            throw new RuntimeExceptionAlias('Internal error, '.$packageName.' already initialised');
        }

        $package = $this->createMock(PackageInterface::class);
        $package
            ->method('getName')
            ->willReturn($packageName);

        // Create the files for the package
        $fileSystem = new Filesystem();
        $installPath = $this->getAbsolutePath($installPath, $this->dirVendor);
        $fileSystem->mkdir($installPath);
        $this->createFiles($files, $installPath);

        // Store the instance so it is pulled from the composer repositories
        // Use
        $this->packageInstances[$packageName] = $package;
        $this->packageInstallPaths[$packageName] = $installPath;

        return $package;
    }

    /**
     * Sets the plugin configuration
     *
     * This configuration is used via the $this->composer nested within a
     * root package that is returned from it.
     *
     * @see setUp()
     *
     * @param array $config
     *    The configuration array including the plugin root key
     *
     * @return void
     */
    protected function setPluginConfig(array $config): void
    {
        $this->pluginConfig = $config;
    }
}
