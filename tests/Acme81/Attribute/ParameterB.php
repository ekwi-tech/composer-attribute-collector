<?php

namespace Acme81\Attribute;

use Attribute;
use Ekwi\ComposerAttributeCollector\CollectableAttribute;

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
