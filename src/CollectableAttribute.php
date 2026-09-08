<?php

namespace Ekwi\ComposerAttributeCollector;

/**
 * Marks an attribute as collectable by the collector.
 *
 * Only attributes marked with this attribute will be collected.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class CollectableAttribute
{
}
