<?php

namespace Ekwi\ComposerAttributeCollector;

use ReflectionClass;
use ReflectionException;

/**
 * @template T of object
 */
final class TargetClass
{
    /**
     * @var T|null
     */
    private ?object $instance = null;

    /**
     * @param class-string<T> $attribute
     *     The name of the attribute class.
     * @param class-string $name
     *     The name of the target class.
     */
    public function __construct(
        private readonly string $attribute,
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
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Instantiates the attribute, using reflection on the target class.
     *
     * The instance is created on first use, then reused.
     *
     * @return T
     *
     * @throws ReflectionException if the target class cannot be reflected.
     */
    public function getAttribute(): object
    {
        return $this->instance ??= AttributeInstantiator::instantiate(
            (new ReflectionClass($this->name))->getAttributes($this->attribute),
            $this->attribute,
            $this->name,
        );
    }
}
