<?php
/**
 * @file
 * SimpleIoLoggerTest.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Log;

use Composer\IO\IOInterface;
use JParkinson1991\ComposerLinkerPlugin\Log\SimpleIoLogger;
use PHPUnit\Framework\TestCase;

/**
 * Class SimpleIoLoggerTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Log
 */
class SimpleIoLoggerTest extends TestCase
{
    /**
     * The mocker io message;
     *
     * @var \Composer\IO\IOInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $io;

    /**
     * The testable logger instance
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Log\SimpleIoLogger
     */
    protected $simpleIoLogger;

    /**
     * Sets up the class for use in the variou test cases
     */
    public function setUp(): void
    {
        $this->io = $this->createMock(IOInterface::class);
        $this->simpleIoLogger = new SimpleIoLogger($this->io);
    }

    /**
     * Tests logging of alerts
     */
    public function testItLogsAlerts()
    {
        $this->expectIoToWrite('Alert');
        $this->simpleIoLogger->alert('Alert');
    }

    /**
     * Tests logging of criticals
     */
    public function testItLogsCriticals()
    {
        $this->expectIoToWrite('Critical');
        $this->simpleIoLogger->critical('Critical');
    }

    /**
     * Tests logging of debugs
     */
    public function testItLogsDebugs()
    {
        $this->expectIoToWrite('Debug');
        $this->simpleIoLogger->debug('Debug');
    }

    /**
     * Tests logging of emergencies
     */
    public function testItLogsEmergencies()
    {
        $this->expectIoToWrite('Emergency');
        $this->simpleIoLogger->emergency('Emergency');
    }

    /**
     * Tests logging of errors
     */
    public function testItLogsErrors()
    {
        $this->expectIoToWrite('Error');
        $this->simpleIoLogger->error('Error');
    }

    /**
     * Tests logging of infos
     */
    public function testItLogsInfo()
    {
        $this->expectIoToWrite('Info');
        $this->simpleIoLogger->info('Info');
    }

    /**
     * Tests logging of notices
     */
    public function testItLogsNotices()
    {
        $this->expectIoToWrite('Notice');
        $this->simpleIoLogger->notice('Notice');
    }

    /**
     * Tests logging of warnings
     */
    public function testItLogsWarnings()
    {
        $this->expectIoToWrite('Warning');
        $this->simpleIoLogger->warning('Warning');
    }

    /**
     * Simplifies creation of method observer on the IO service.
     */
    protected function expectIoToWrite(string $message)
    {
        $this->io->expects($this->once())
            ->method('write')
            ->with($message);
    }
}
