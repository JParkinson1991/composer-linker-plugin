<?php
/**
 * @file
 * ArrayTestTrait.php
 */

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
}
