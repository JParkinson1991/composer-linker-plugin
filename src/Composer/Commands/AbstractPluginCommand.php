<?php
/**
 * @file
 * LinkCommand.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Composer\Commands;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\ConsoleIO;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Util\Filesystem as ComposerFilesystem;
use InvalidArgumentException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorException;
use JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorExceptionCollection;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinitionFactory;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkFileHandler;
use JParkinson1991\ComposerLinkerPlugin\Log\SimpleIoLogger;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * Class LinkCommand
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Composer\Commands
 */
abstract class AbstractPluginCommand extends BaseCommand
{
    /**
     * Returns the name stub to automatically be applied to full command names
     * and aliases
     *
     * @return string
     */
    abstract protected function nameStub(): string;

    /**
     * Returns the description of the command
     *
     * @return string
     */
    abstract protected function description(): string;

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
    abstract protected function doExecutePackage(LinkExecutor $linkExecutor, PackageInterface $package): void;

    /**
     * Runs the actual execution of a repository against the link executor
     * within the context of the command.
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor $linkExecutor
     *     The link executor
     * @param \Composer\Repository\RepositoryInterface $repository
     *     The repository to execute
     *
     * @throws \JParkinson1991\ComposerLinkerPlugin\Exception\LinkExecutorExceptionCollection
     *
     * @return void
     */
    abstract protected function doExecuteRepository(LinkExecutor $linkExecutor, RepositoryInterface $repository): void;

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('composer-linker-plugin:'.$this->nameStub());
        $this->setAliases(['clp-'.$this->nameStub()]);
        $this->setDescription($this->description());
        $this->addArgument(
            'package-names',
            InputArgument::IS_ARRAY,
            'Optional list of package names to process only. Space separated'
        );
    }

    /**
     * @inheritDoc
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get compose, ensure it could be loaded
        $composer = $this->getComposer(true);
        if ($composer === null) {
            $output->writeln('<error>Error</error> Failed to load composer');

            return 1;
        }

        // Instantiate the link executor, fetch the local repository from
        // composer and get the package names argument passed to the command
        $linkExecutor = $this->createLinkExecutor($composer, $input, $output);
        $repository = $composer->getRepositoryManager()->getLocalRepository();
        $packageNames = $input->getArgument('package-names');

        // If no package names provided, execute against the full repository
        if (empty($packageNames)) {
            return $this->executeRepository(
                $linkExecutor,
                $repository,
                $output
            );
        }

        // Command names provided, try load package and execute each of them
        try {
            $packages = $this->loadPackages($packageNames, $repository);

            return $this->executePackages($packages, $linkExecutor, $output);
        }
        catch (InvalidArgumentException $e) {
            $output->writeln('<error>Error</error> '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Executes processing for an entire repository
     *
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor $linkExecutor
     *     The link executor
     * @param \Composer\Repository\RepositoryInterface $repository
     *     The repository containing the packages to process
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *     The output used to write status messages to caller
     *
     * @return int
     *     The command exit code
     */
    private function executeRepository(
        LinkExecutor $linkExecutor,
        RepositoryInterface $repository,
        OutputInterface $output
    ): int {
        // Try route processing to extending command
        // Catching errors and writing status messages to caller
        try {
            $this->doExecuteRepository($linkExecutor, $repository);

            $output->writeln('<info>Process completed</info>');

            return 0;
        }
        catch (LinkExecutorExceptionCollection $e) {
            foreach ($e->getExceptions() as $exception) {
                $output->writeln('<error>Error</error> '.$exception->getExecutionException()->getMessage());
            }

            $output->writeln('Process completed with errors');

            return 1;
        }
    }

    /**
     * Executes processing of an array of packages.
     *
     * Called when the commands are provided arguments
     *
     * @param \Composer\Package\PackageInterface[] $packages
     *     The packages to process
     * @param \JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor $linkExecutor
     *     The link executor
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *     The output used to write status messages to the caller
     *
     * @return int
     */
    private function executePackages(array $packages, LinkExecutor $linkExecutor, OutputInterface $output): int
    {
        // Initialise error indication for final status output
        $hasError = false;

        // Loop each package passing to sub command to execute
        // Catch errors and output
        foreach ($packages as $package) {
            try {
                $this->doExecutePackage($linkExecutor, $package);
            }
            catch (LinkExecutorException $e) {
                $output->writeln('<error>Error</error> '.$e->getExecutionException()->getMessage());
                $hasError = true;
            }
        }

        // If processing completed with errors output status, return error
        // exit code
        if ($hasError) {
            $output->writeln('Process completed with errors');

            return 1;
        }

        // No errors
        $output->writeln('<info>Process completed</info>');

        return 0;
    }

    /**
     * Instantiates a LinkExecutorInstance
     *
     * @param \Composer\Composer $composer
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return \JParkinson1991\ComposerLinkerPlugin\Link\LinkExecutor
     *
     * @throws \Exception
     */
    // phpcs:ignore
    private function createLinkExecutor(Composer $composer, InputInterface $input, OutputInterface $output): LinkExecutor
    {
        $linkDefinitionFactory = new LinkDefinitionFactory($composer->getPackage());
        $linkFileHandler = new LinkFileHandler(
            new SymfonyFilesystem(),
            new ComposerFilesystem(),
            $composer->getInstallationManager()
        );

        // Configure file handler to log to console output
        $linkFileHandler->setLogger(new SimpleIoLogger(new ConsoleIO(
            $input,
            $output,
            $this->getHelperSet()
        )));

        return new LinkExecutor($linkDefinitionFactory, $linkFileHandler);
    }

    /**
     * Loads and returns package objects from a repository using a list of
     * package names.
     *
     * @param string[] $packageNames
     * @param \Composer\Repository\RepositoryInterface $repository
     *
     * @return \Composer\Package\PackageInterface[]
     */
    private function loadPackages(array $packageNames, RepositoryInterface $repository): array
    {
        $packages = [];
        foreach ($packageNames as $packageName) {
            $foundPackages = $repository->findPackages($packageName);

            if (count($foundPackages) === 0) {
                throw new InvalidArgumentException(
                    'Failed to find package <info>'.$packageName.'</info>'
                );
            }

            if (count($foundPackages) > 1) {
                throw new InvalidArgumentException(
                    'Found multiple packages for <info>'.$packageName.'</info>. Be more specific'
                );
            }

            $packages[] = $foundPackages[0];
        }

        return $packages;
    }
}
