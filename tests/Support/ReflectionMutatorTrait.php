<?php
/**
 * @file
 * ReflectionMutatorTrait.php
 */

declare(strict_types=1);

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

    /**
     * Sets a property value against a given object by reflection
     *
     * @param object $object
     *     The object set the value against
     * @param string $propertyName
     *     The name of the property to set the value on
     * @param mixed $value
     *     The value to set
     *
     * @return void
     *
     * @throws \ReflectionException
     */
    protected function setPropertyValue($object, string $propertyName, $value)
    {
        $property = new \ReflectionProperty(get_class($object), $propertyName);
        $property->setAccessible(true);

        $property->setValue($object, $value);
    }

    /**
     * Calls methods on a given object using reflection
     *
     * @param object $object
     *     The object to call the method on
     * @param string $methodName
     *     The name of the method to call on the object
     * @param mixed ...$arguments
     *     Any number of arguments to pass to the method
     *
     * @return mixed
     *     The result of the method id any
     *
     * @throws \ReflectionException
     */
    protected function callObjectMethod($object, $methodName, ...$arguments)
    {
        $method = new \ReflectionMethod(get_class($object), $methodName);
        $method->setAccessible(true);

        return $method->invoke($object, ...$arguments);
    }
}
