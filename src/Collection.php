<?php

namespace olvlvl\ComposerAttributeCollector;

use function array_map;

/**
 * @internal
 */
final class Collection
{
    /**
     * @param array<class-string, array<class-string>> $targetClasses
     *     Where _key_ is an attribute class and _value_ an array of target classes.
     * @param array<class-string, array<array{ class-string, non-empty-string }>> $targetMethods
     *     Where _key_ is an attribute class and _value_ an array of arrays
     *     where 0 is a target class and 1 is the target method.
     * @param array<class-string, array<array{ class-string, non-empty-string }>> $targetProperties
     *     Where _key_ is an attribute class and _value_ an array of arrays
     *     where 0 is a target class and 1 is the target property.
     * @param array<class-string, array<array{ class-string, non-empty-string, non-empty-string }>> $targetParameters
     *     Where _key_ is an attribute class and _value_ an array of arrays
     *     where 0 is a target class, 1 is the target method, and 2 is the target parameter.
     */
    public function __construct(
        private array $targetClasses,
        private array $targetMethods,
        private array $targetProperties,
        private array $targetParameters,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return array<TargetClass<T>>
     */
    public function findTargetClasses(string $attribute): array
    {
        return array_map(
            fn(string $class) => new TargetClass($attribute, $class),
            $this->targetClasses[$attribute] ?? [],
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return array<TargetMethod<T>>
     */
    public function findTargetMethods(string $attribute): array
    {
        return array_map(
            fn(array $t) => new TargetMethod($attribute, ...$t),
            $this->targetMethods[$attribute] ?? [],
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return array<TargetParameter<T>>
     */
    public function findTargetParameters(string $attribute): array
    {
        return array_map(
            fn(array $t) => new TargetParameter($attribute, ...$t),
            $this->targetParameters[$attribute] ?? [],
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return array<TargetProperty<T>>
     */
    public function findTargetProperties(string $attribute): array
    {
        return array_map(
            fn(array $t) => new TargetProperty($attribute, ...$t),
            $this->targetProperties[$attribute] ?? [],
        );
    }

    /**
     * @param callable(class-string $attribute, class-string $class):bool $predicate
     *
     * @return array<TargetClass<object>>
     */
    public function filterTargetClasses(callable $predicate): array
    {
        $ar = [];

        foreach ($this->targetClasses as $attribute => $classes) {
            foreach ($classes as $class) {
                if ($predicate($attribute, $class)) {
                    $ar[] = new TargetClass($attribute, $class);
                }
            }
        }

        return $ar;
    }

    /**
     * @param callable(class-string $attribute, class-string $class, non-empty-string $method):bool $predicate
     *
     * @return array<TargetMethod<object>>
     */
    public function filterTargetMethods(callable $predicate): array
    {
        $ar = [];

        foreach ($this->targetMethods as $attribute => $references) {
            foreach ($references as [$class, $method]) {
                if ($predicate($attribute, $class, $method)) {
                    $ar[] = new TargetMethod($attribute, $class, $method);
                }
            }
        }

        return $ar;
    }

    /**
     * @param callable(class-string $attribute, class-string $class, non-empty-string $method, non-empty-string $parameter):bool $predicate
     *
     * @return array<TargetParameter<object>>
     */
    public function filterTargetParameters(callable $predicate): array
    {
        $ar = [];

        foreach ($this->targetParameters as $attribute => $references) {
            foreach ($references as [$class, $method, $parameter]) {
                if ($predicate($attribute, $class, $method, $parameter)) {
                    $ar[] = new TargetParameter($attribute, $class, $method, $parameter);
                }
            }
        }

        return $ar;
    }

    /**
     * @param callable(class-string $attribute, class-string $class, non-empty-string $property):bool $predicate
     *
     * @return array<TargetProperty<object>>
     */
    public function filterTargetProperties(callable $predicate): array
    {
        $ar = [];

        foreach ($this->targetProperties as $attribute => $references) {
            foreach ($references as [$class, $property]) {
                if ($predicate($attribute, $class, $property)) {
                    $ar[] = new TargetProperty($attribute, $class, $property);
                }
            }
        }

        return $ar;
    }

    /**
     * @param class-string $class
     */
    public function forClass(string $class): ForClass
    {
        $classAttributes = [];

        foreach ($this->filterTargetClasses(fn($a, $c): bool => $c === $class) as $targetClass) {
            $classAttributes[] = $targetClass->attribute;
        }

        $methodAttributes = [];

        foreach ($this->filterTargetMethods(fn($a, $c): bool => $c === $class) as $targetMethod) {
            $methodAttributes[$targetMethod->name][] = $targetMethod->attribute;
        }

        $propertyAttributes = [];

        foreach ($this->filterTargetProperties(fn($a, $c): bool => $c === $class) as $targetProperty) {
            $propertyAttributes[$targetProperty->name][] = $targetProperty->attribute;
        }

        return new ForClass(
            $classAttributes,
            $methodAttributes,
            $propertyAttributes,
        );
    }
}
