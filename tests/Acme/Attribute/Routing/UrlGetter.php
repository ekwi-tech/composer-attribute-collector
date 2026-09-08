<?php

namespace Acme\Attribute\Routing;

use Attribute;
use Ekwi\ComposerAttributeCollector\CollectableAttribute;

#[CollectableAttribute]
#[Attribute(Attribute::TARGET_METHOD)]
final class UrlGetter
{
}
