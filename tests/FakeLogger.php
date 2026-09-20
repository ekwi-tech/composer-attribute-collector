<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Ekwi\ComposerAttributeCollector\Logger;

final class FakeLogger implements Logger
{
    /**
     * @var string[]
     */
    public array $warnings = [];

    public function debug(\Stringable|string $message): void
    {
    }

    public function warning(\Stringable|string $message): void
    {
        $this->warnings[] = (string) $message;
    }

    public function error(\Stringable|string $message): void
    {
    }
}
