<?php

namespace Ekwi\ComposerAttributeCollector;

/**
 * @readonly
 * @internal
 */
final class TransientTargetProperty
{
    /**
     * @param class-string $attribute The attribute class.
     * @param non-empty-string $name The target property.
     */
    public function __construct(
        public string $attribute,
        public string $name,
    ) {
    }
}
