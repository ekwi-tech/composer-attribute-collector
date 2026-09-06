<?php

namespace olvlvl\ComposerAttributeCollector;

/**
 * @readonly
 *
 * @template T of object
 */
final class TargetParameter
{
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
        public string $attribute,
        public string $class,
        public string $method,
        public string $name,
    ) {
    }
}
