<?php
/**
 * @file
 * LinkDefinitionTest.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Unit\Link;

use Composer\Package\PackageInterface;
use InvalidArgumentException;
use JParkinson1991\ComposerLinkerPlugin\Link\LinkDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Class LinkDefinitionTest
 *
 * @package JParkinson1991\Tests\Unit\Link
 */
class LinkDefinitionTest extends TestCase
{
    /**
     * Tests link definitions are instantiated as expected
     */
    public function testInstantiation(): void
    {
        $package = $this->createMock(PackageInterface::class);

        $linkDefinition = new LinkDefinition($package, '/destination/dir');

        $this->assertSame($package, $linkDefinition->getPackage());
        $this->assertSame('/destination/dir', $linkDefinition->getDestinationDir());
    }

    /**
     * Tests that in it's default state link definitions to not copy files
     */
    public function testItDoesNotCopyFilesByDefault(): void
    {
        $linkDefinition = new LinkDefinition(
            $this->createMock(PackageInterface::class),
            '/destination/dir'
        );

        $this->assertFalse($linkDefinition->getCopyFiles());
    }

    /**
     * Tests that copying of linked files can be enabled after instantiation
     * of a link definition
     */
    public function testCopyFilesCanBeSetAfterInstantiation(): void
    {
        $linkDefinition = new LinkDefinition(
            $this->createMock(PackageInterface::class),
            '/destination/dir'
        );

        $linkDefinition->setCopyFiles(true);
        $this->assertTrue($linkDefinition->getCopyFiles());

        $linkDefinition->setCopyFiles(false);
        $this->assertFalse($linkDefinition->getCopyFiles());
    }

    /**
     * Tests that by default orphan directories of the linked files should
     * not be deleted automatically.
     */
    public function testItDoesNotDeleteOrphanDirectoriesByDefault(): void
    {
        $linkDefinition = new LinkDefinition(
            $this->createMock(PackageInterface::class),
            'destination/dir'
        );

        $this->assertFalse($linkDefinition->getDeleteOrphanDirs());
    }

    /**
     * Tests that deletion of orphan directories can be deleted instantiation.
     */
    public function testDeleteOrphanDirectoriesCanBeSetAfterInstantiation()
    {
        $linkDefinition = new LinkDefinition(
            $this->createMock(PackageInterface::class),
            'destination/dir'
        );

        $linkDefinition->setDeleteOrphanDirs(true);
        $this->assertTrue($linkDefinition->getDeleteOrphanDirs());

        $linkDefinition->setDeleteOrphanDirs(false);
        $this->assertFalse($linkDefinition->getDeleteOrphanDirs());
    }

    /**
     * Tests that specific files can be set against the link definition and
     * returned in the expected format.
     */
    public function testSpecificFilesCanBeAddedAndReturned(): void
    {
        $linkDefinition = new LinkDefinition(
            $this->createMock(PackageInterface::class),
            '/destination/dir'
        );

        // Assert no mappings by default
        $this->assertCount(0, $linkDefinition->getFileMappings());

        // Add a single mapping, assert added correctly
        $linkDefinition->addFileMapping('source.txt', 'dest.txt');
        $fileMappings = $linkDefinition->getFileMappings();
        $this->assertCount(1, $fileMappings);
        $this->assertArrayHasKey('source.txt', $fileMappings);
        $this->assertCount(1, $fileMappings['source.txt']);
        $this->assertContains('dest.txt', $fileMappings['source.txt']);

        // Add a new destination for the source file, assert added as expected
        // Original mapping must still exist
        $linkDefinition->addFileMapping('source.txt', 'another-dest.txt');
        $fileMappings = $linkDefinition->getFileMappings();
        $this->assertCount(1, $fileMappings);
        $this->assertArrayHasKey('source.txt', $fileMappings);
        $this->assertCount(2, $fileMappings['source.txt']);
        $this->assertContains('dest.txt', $fileMappings['source.txt']);
        $this->assertContains('another-dest.txt', $fileMappings['source.txt']);

        // Add new source, assert both mapping sets exist
        $linkDefinition->addFileMapping('second-source.txt', 'second-dest.txt');
        $fileMappings = $linkDefinition->getFileMappings();
        $this->assertCount(2, $fileMappings);
        $this->assertArrayHasKey('source.txt', $fileMappings);
        $this->assertCount(2, $fileMappings['source.txt']);
        $this->assertContains('dest.txt', $fileMappings['source.txt']);
        $this->assertContains('another-dest.txt', $fileMappings['source.txt']);
        $this->assertArrayHasKey('second-source.txt', $fileMappings);
        $this->assertCount(1, $fileMappings['second-source.txt']);
        $this->assertContains('second-dest.txt', $fileMappings['second-source.txt']);

        // Add the same mapping as already added initially, first call to
        // add method, this should not be trigger exceptions and be recognised
        // as a duplicate call which silently exists without adding extra
        // unneeded mappings. After trying to re add the source and dest file
        // again returned structure should be as above with no new entry for
        // source.txt
        $exceptionCaught = false;
        try {
            // Duplicate entry
            $linkDefinition->addFileMapping('source.txt', 'dest.txt');
        }
        catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertFalse(
            $exceptionCaught,
            'Invalid argument exception thrown when adding same source/dest
            pairing twice. Should be ignored.'
        );
        $this->assertCount(2, $fileMappings);
        $this->assertArrayHasKey('source.txt', $fileMappings);
        $this->assertCount(2, $fileMappings['source.txt']);
        $this->assertContains('dest.txt', $fileMappings['source.txt']);
        $this->assertContains('another-dest.txt', $fileMappings['source.txt']);
        $this->assertArrayHasKey('second-source.txt', $fileMappings);
        $this->assertCount(1, $fileMappings['second-source.txt']);
        $this->assertContains('second-dest.txt', $fileMappings['second-source.txt']);

        // Add another mapping to the second source with a previously used
        // destination, expect invalid argument exception
        $exceptionCaught = false;
        try {
            $linkDefinition->addFileMapping('second-source.txt', 'dest.txt');
        }
        catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue(
            $exceptionCaught,
            'Invalid argument exception not thrown when adding same destination to second-source'
        );

        // Add a new source with a previously used destination, expect invalid
        // argument exception
        $exceptionCaught = false;
        try {
            $linkDefinition->addFileMapping('third-source.txt', 'dest.txt');
        }
        catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue(
            $exceptionCaught,
            'Invalid argument exception not thrown when adding same destination with a new source'
        );
    }
}
