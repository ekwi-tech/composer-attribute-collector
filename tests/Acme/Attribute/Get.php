<?php

namespace Acme\Attribute;

use Attribute;
use olvlvl\ComposerAttributeCollector\CollectableAttribute;

#[CollectableAttribute]
#[Attribute(Attribute::TARGET_METHOD)]
class Get extends Route
{
    public function __construct(
        string $pattern = '',
        ?string $id = null
    ) {
        parent::__construct($pattern, 'GET', $id);
    }
}
