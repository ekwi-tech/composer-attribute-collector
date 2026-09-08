<?php

namespace Ekwi\ComposerAttributeCollector;

/**
 * @readonly
 * @internal
 */
final class TransientTargetParameter
{
    /**
     * @param class-string $attribute The attribute class.
     * @param non-empty-string $method The target method.
     * @param non-empty-string $name The target parameter.
     */
    public function __construct(
        public string $attribute,
        public string $method,
        public string $name,
    ) {
    }
}
