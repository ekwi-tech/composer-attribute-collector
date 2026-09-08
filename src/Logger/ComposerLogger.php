<?php

namespace Ekwi\ComposerAttributeCollector\Logger;

use Composer\IO\IOInterface;
use Ekwi\ComposerAttributeCollector\Logger;

/**
 * @internal
 * @readonly
 */
final class ComposerLogger implements Logger
{
    public function __construct(
        private IOInterface $io
    ) {
    }

    public function debug(\Stringable|string $message): void
    {
        $this->io->debug($message);
    }

    public function warning(\Stringable|string $message): void
    {
        $this->io->warning($message);
    }

    public function error(\Stringable|string $message): void
    {
        $this->io->error($message);
    }
}
