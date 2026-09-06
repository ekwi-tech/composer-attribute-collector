<?php

namespace olvlvl\ComposerAttributeCollector;

/**
 * @readonly
 *
 * @template T of object
 */
final class TargetProperty
{
    /**
     * @param class-string<T> $attribute
     *     The name of the attribute class.
     * @param class-string $class
     *     The name of the target class.
     * @param non-empty-string $name
     *     The name of the target property.
     */
    public function __construct(
        public string $attribute,
        public string $class,
        public string $name,
    ) {
    }
}
