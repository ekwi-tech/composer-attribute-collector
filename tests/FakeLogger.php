<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Ekwi\ComposerAttributeCollector\Logger;

final class FakeLogger implements Logger
{
    public function debug(\Stringable|string $message): void
    {
    }

    public function warning(\Stringable|string $message): void
    {
    }

    public function error(\Stringable|string $message): void
    {
    }
}
