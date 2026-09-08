<?php

namespace Ekwi\ComposerAttributeCollector;

use ReflectionAttribute;
use ReflectionException;
use ReflectionMethod;

/**
 * @template T of object
 */
final class TargetParameter
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
     * @param non-empty-string $method
     *      The name of the target method.
     * @param non-empty-string $name
     *     The name of the target parameter.
     */
    public function __construct(
        private readonly string $attribute,
        private readonly string $class,
        private readonly string $method,
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
     *     The name of the target method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return non-empty-string
     *     The name of the target parameter.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Instantiates the attribute, using reflection on the target parameter.
     *
     * The instance is created on first use, then reused.
     *
     * @return T
     *
     * @throws ReflectionException if the target method cannot be reflected.
     */
    public function getAttribute(): object
    {
        return $this->instance ??= AttributeInstantiator::instantiate(
            $this->collectParameterAttributes(),
            $this->attribute,
            "$this->class::$this->method($this->name)",
        );
    }

    /**
     * @return array<ReflectionAttribute<T>>
     *
     * @throws ReflectionException if the target method cannot be reflected.
     */
    private function collectParameterAttributes(): array
    {
        foreach ((new ReflectionMethod($this->class, $this->method))->getParameters() as $parameter) {
            if ($parameter->name === $this->name) {
                return $parameter->getAttributes($this->attribute);
            }
        }

        return [];
    }
}
