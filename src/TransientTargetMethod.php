<?php

namespace Ekwi\ComposerAttributeCollector;

/**
 * @readonly
 * @internal
 */
final class TransientTargetMethod
{
    /**
     * @param class-string $attribute The attribute class.
     * @param non-empty-string $name The target method.
     */
    public function __construct(
        public string $attribute,
        public string $name,
    ) {
    }
}
