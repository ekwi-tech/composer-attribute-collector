<?php

namespace Acme\Attribute\ActiveRecord;

use Attribute;
use Ekwi\ComposerAttributeCollector\CollectableAttribute;

#[CollectableAttribute]
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Varchar implements SchemaAttribute
{
    public function __construct(
        public int $size = 255,
        public bool $null = false,
        public bool $unique = false,
        public ?string $collate = null,
    ) {
    }
}
