<?php
/**
 * @file
 * LoggerAwareTrait.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class LoggerAwareTrait
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Log
 */
trait LoggerAwareTrait
{
    use \Psr\Log\LoggerAwareTrait;

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function logEmergency($message, array $context = []): void
    {
        $this->doLog(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function logAlert($message, array $context = []): void
    {
        $this->doLog(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function logCritical($message, array $context = []): void
    {
        $this->doLog(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function logError($message, array $context = []): void
    {
        $this->doLog(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function logWarning($message, array $context = []): void
    {
        $this->doLog(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function logNotice($message, array $context = []): void
    {
        $this->doLog(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function logInfo($message, array $context = []): void
    {
        $this->doLog(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function logDebug($message, array $context = []): void
    {
        $this->doLog(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level if a logger is available.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function doLog($level, $message, array $context = []): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, $message, $context);
        }
    }
}
