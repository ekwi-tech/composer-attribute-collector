<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Ekwi\ComposerAttributeCollector\Config;
use Ekwi\ComposerAttributeCollector\Plugin;

final class PluginTest extends CollectorTestAbstract
{
    protected static function dump(Config $config): void
    {
        Plugin::dump($config);
    }
}
