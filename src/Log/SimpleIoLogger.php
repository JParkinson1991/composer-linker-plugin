<?php
/**
 * @file
 * IoLogger.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Log;

use Composer\IO\IOInterface;
use Psr\Log\AbstractLogger;

/**
 * Class IoLogger
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Log
 */
class SimpleIoLogger extends AbstractLogger
{
    /**
     * The local io instance
     *
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * IoLogger constructor.
     *
     * @param \Composer\IO\IOInterface $io
     */
    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = [])
    {
        $this->io->write($message);
    }
}
