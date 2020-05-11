<?php
/**
 * @file
 * LoggerAwareTraitTest.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Log;

use JParkinson1991\ComposerLinkerPlugin\Log\LoggerAwareTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class LoggerAwareTraitTest
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Log
 */
class LoggerAwareTraitTest extends TestCase
{
    /**
     * The logger service added to the trait.
     *
     * @var \PHPUnit\Framework\MockObject\MockObject|\Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The logger trait being tested
     *
     * @var \JParkinson1991\ComposerLinkerPlugin\Log\LoggerAwareTrait|object
     */
    protected $loggerTrait;

    /**
     * Initialise mocked/test object prior to each test run
     */
    public function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->loggerTrait = $this->getObjectForTrait(LoggerAwareTrait::class);
        $this->loggerTrait->setLogger($this->logger);
    }

    /**
     * Tests logging of alerts
     */
    public function testItLogsAlerts()
    {
        $this->expect(LogLevel::ALERT, 'Alert');
        $this->execute('logAlert', 'Alert');
    }

    /**
     * Tests logging of criticals
     */
    public function testItLogsCriticals()
    {
        $this->expect(LogLevel::CRITICAL, 'Critical');
        $this->execute('logCritical', 'Critical');
    }

    /**
     * Tests logging of debugs
     */
    public function testItLogsDebugs()
    {
        $this->expect(LogLevel::DEBUG, 'Debug');
        $this->execute('logDebug', 'Debug');
    }

    /**
     * Tests logging of emergencies
     */
    public function testItLogsEmergencies()
    {
        $this->expect(LogLevel::EMERGENCY, 'Emergency');
        $this->execute('logEmergency', 'Emergency');
    }

    /**
     * Tests logging of errors
     */
    public function testItLogsErrors()
    {
        $this->expect(LogLevel::ERROR, 'Error');
        $this->execute('logError', 'Error');
    }

    /**
     * Tests logging of infos
     */
    public function testItLogsInfo()
    {
        $this->expect(LogLevel::INFO, 'Info');
        $this->execute('logInfo', 'Info');
    }

    /**
     * Tests logging of notices
     */
    public function testItLogsNotices()
    {
        $this->expect(LogLevel::NOTICE, 'Notice');
        $this->execute('logNotice', 'Notice');
    }

    /**
     * Tests logging of warnings
     */
    public function testItLogsWarnings()
    {
        $this->expect(LogLevel::WARNING, 'Warning');
        $this->execute('logWarning', 'Warning');
    }

    /**
     * Initialise the observer on the internal logger set against the trait
     * being tested
     *
     * @param string $level
     *     The expected log level
     * @param string $message
     *     The expected message
     */
    protected function expect(string $level, $message)
    {
        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                $level,
                $message,
                []
            );
    }

    /**
     * Executes the protected method on the trait being tested
     *
     * @param string $method
     *     The method name
     * @param string $message
     *     The message being logged
     *
     * @throws \ReflectionException
     */
    protected function execute(string $method, string $message)
    {
        $method = new \ReflectionMethod(get_class($this->loggerTrait), $method);
        $method->setAccessible(true);
        $method->invoke($this->loggerTrait, $message);
    }

}
