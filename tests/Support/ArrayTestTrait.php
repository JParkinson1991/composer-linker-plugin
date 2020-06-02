<?php
/**
 * @file
 * ArrayTestTrait.php
 */

declare(strict_types=1);

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Support;

/**
 * Trait ArrayTestTrait
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Support
 */
trait ArrayTestTrait
{
    /**
     * Asserts that two arrays are the same whilst ignoring ordering of keys
     * etc.
     *
     * @return void
     */
    protected function assertArraySame(array $expected, array $actual)
    {
        $this->assertEquals([], array_diff_key($actual, $expected), 'Found differing keys');

        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                $this->assertArraySame($value, $actual[$key]);
            }
            else {
                $this->assertContains($value, $actual);
            }
        }
    }

    /**
     * Asserts that an array contains an instance of the given class name
     *
     * @param string $expected
     *     The name of the class/interface to check for
     * @param array $array
     *     An array that may can on object of $expected type
     *
     * @return void
     */
    protected function assertArrayContainsInstanceOf(string $expected, array $array)
    {
        $countMatch = 0;
        foreach ($array as $object) {
            if (!is_object($object)) {
                continue;
            }

            if (is_a($object, $expected)) {
                $countMatch++;
            }
        }

        $this->assertGreaterThan(0, $countMatch, 'Failed to find '.$expected.' in array');
    }
}
