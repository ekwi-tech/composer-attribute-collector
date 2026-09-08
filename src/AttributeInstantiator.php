<?php

namespace Ekwi\ComposerAttributeCollector;

use LogicException;
use ReflectionAttribute;

/**
 * Instantiates the attribute of a target, using reflection.
 *
 * @internal
 */
final class AttributeInstantiator
{
    /**
     * @template T of object
     *
     * @param array<ReflectionAttribute<T>> $attributes
     *     The attributes of the target, filtered by attribute class.
     * @param class-string<T> $attribute
     *     The name of the attribute class.
     * @param string $target
     *     A human-readable representation of the target, used for error reporting.
     *
     * @return T
     *     The instance of the first matching attribute.
     */
    public static function instantiate(array $attributes, string $attribute, string $target): object
    {
        $reflection = reset($attributes)
            ?: throw new LogicException(
                "Unable to find the attribute $attribute on $target,"
                . " the attributes file might be out of date."
            );

        return $reflection->newInstance();
    }
}
