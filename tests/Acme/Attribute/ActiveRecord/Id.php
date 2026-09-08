<?php

namespace Acme\Attribute\ActiveRecord;

use Attribute;
use Ekwi\ComposerAttributeCollector\CollectableAttribute;

/**
 * Marks one or multiple properties that constitute the record identifier i.e. the primary key in the database.
 */
#[CollectableAttribute]
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Id implements SchemaAttribute
{
}
