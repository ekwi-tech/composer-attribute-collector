<?php

namespace Ekwi\ComposerAttributeCollector;

use ReflectionException;
use ReflectionProperty;

/**
 * @template T of object
 */
final class TargetProperty
{
    /**
     * @var T|null
     */
    private ?object $instance = null;

    /**
     * @param class-string<T> $attribute
     *     The name of the attribute class.
     * @param class-string $class
     *     The name of the target class.
     * @param non-empty-string $name
     *     The name of the target property.
     */
    public function __construct(
        private readonly string $attribute,
        private readonly string $class,
        private readonly string $name,
    ) {
    }

    /**
     * @return class-string<T>
     *     The name of the attribute class.
     */
    public function getAttributeClass(): string
    {
        return $this->attribute;
    }

    /**
     * @return class-string
     *     The name of the target class.
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return non-empty-string
     *     The name of the target property.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Instantiates the attribute, using reflection on the target property.
     *
     * The instance is created on first use, then reused.
     *
     * @return T
     *
     * @throws ReflectionException if the target property cannot be reflected.
     */
    public function getAttribute(): object
    {
        return $this->instance ??= AttributeInstantiator::instantiate(
            (new ReflectionProperty($this->class, $this->name))->getAttributes($this->attribute),
            $this->attribute,
            "$this->class::$this->name",
        );
    }
}
