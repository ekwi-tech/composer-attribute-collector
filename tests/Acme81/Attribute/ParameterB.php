<?php

namespace Acme81\Attribute;

use Attribute;
use olvlvl\ComposerAttributeCollector\CollectableAttribute;

#[CollectableAttribute]
#[Attribute(Attribute::TARGET_PARAMETER)]
class ParameterB
{
    public function __construct(
        public string $label = '',
        public string $moreData = ''
    ) {
    }
}
