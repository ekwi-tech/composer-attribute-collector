<?php

namespace olvlvl\ComposerAttributeCollector;

/**
 * @readonly
 *
 * @template T of object
 */
final class TargetClass
{
    /**
     * @param class-string<T> $attribute
     *     The name of the attribute class.
     * @param class-string $name
     *     The name of the target class.
     */
    public function __construct(
        public string $attribute,
        public string $name,
    ) {
    }
}
