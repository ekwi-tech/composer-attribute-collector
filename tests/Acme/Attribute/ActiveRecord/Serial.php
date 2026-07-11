<?php

namespace Acme\Attribute\ActiveRecord;

use Attribute;
use olvlvl\ComposerAttributeCollector\CollectableAttribute;

#[CollectableAttribute]
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Serial implements SchemaAttribute
{
}
