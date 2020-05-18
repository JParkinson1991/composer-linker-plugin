<?php
/**
 * @file
 * ReflectionMutatorTrait.php
 */

namespace JParkinson1991\ComposerLinkerPlugin\Tests\Support;

/**
 * Trait ReflectionMutatorTrait
 *
 * @package JParkinson1991\ComposerLinkerPlugin\Tests\Support
 */
trait ReflectionMutatorTrait
{
    /**
     * Returns the value of property by name for the given object
     *
     * @param object $object
     *     The object to get the value from
     * @param string $propertName
     *     The name of the property holding the value
     */
    protected function getPropertyValue($object, string $propertyName)
    {
        $property = new \ReflectionProperty(get_class($object), $propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    protected function setPropertyValue($object, string $propertyName, $value)
    {
        $property = new \ReflectionProperty(get_class($object), $propertyName);
        $property->setAccessible(true);

        $property->setValue($object, $value);
    }
}
