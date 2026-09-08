<?php

namespace Ekwi\ComposerAttributeCollector;

/**
 * @readonly
 */
final class ForClass
{
    /**
     * @param iterable<class-string> $classAttributes
     *     Where _value_ is the name of an attribute class.
     * @param array<string, iterable<class-string>> $methodsAttributes
     *     Where _key_ is a method and _value_ an iterable where _value_ is the name of an
     *     attribute class.
     * @param array<string, iterable<class-string>> $propertyAttributes
     *     Where _key_ is a property and _value_ an iterable where _value_ is the name of an
     *     attribute class.
     */
    public function __construct(
        public iterable $classAttributes,
        public array $methodsAttributes,
        public array $propertyAttributes,
    ) {
    }
}
