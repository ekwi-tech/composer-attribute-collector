<?php

namespace olvlvl\ComposerAttributeCollector;

/**
 * @readonly
 * @internal
 */
final class TransientTargetClass
{
    /**
     * @param class-string $attribute The attribute class.
     */
    public function __construct(
        public string $attribute,
    ) {
    }
}
