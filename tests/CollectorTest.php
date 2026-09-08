<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Ekwi\ComposerAttributeCollector\Collector;
use Ekwi\ComposerAttributeCollector\Config;

final class CollectorTest extends CollectorTestAbstract
{
    protected static function dump(Config $config): void
    {
        $collector = new Collector($config, new FakeLogger());
        $collector->dump();
    }
}
