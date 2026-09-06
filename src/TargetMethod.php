<?php

namespace olvlvl\ComposerAttributeCollector;

/**
 * @readonly
 *
 * @template T of object
 */
final class TargetMethod
{
    /**
     * @param class-string<T> $attribute
     *     The name of the attribute class.
     * @param class-string $class
     *     The name of the target class.
     * @param non-empty-string $name
     *     The name of the target method.
     */
    public function __construct(
        public string $attribute,
        public string $class,
        public string $name,
    ) {
    }
}
