<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Acme\PSR4\Presentation\ArticleController;
use Ekwi\ComposerAttributeCollector\ClassAttributeCollector;
use Ekwi\ComposerAttributeCollector\Datastore\RuntimeDatastore;
use Ekwi\ComposerAttributeCollector\MemoizeAttributeCollector;
use PHPUnit\Framework\TestCase;

final class MemoizeAttributeCollectorTest extends TestCase
{
    /**
     * An archive extracted in place can restore an mtime older than the last run, which is a change too.
     */
    public function testAClassIsCollectedAgainWhenItsMtimeChangesEitherWay(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'memoize-attributes-');
        \assert(\is_string($file));
        $datastore = new RuntimeDatastore();
        $counting = new class (new FakeLogger()) extends ClassAttributeCollector {
            public int $calls = 0;

            public function collectAttributes(string $class): array
            {
                $this->calls++;

                return parent::collectAttributes($class);
            }
        };
        $run = function () use ($counting, $datastore, $file): void {
            (new MemoizeAttributeCollector($counting, $datastore, new FakeLogger()))
                ->collectAttributes([ ArticleController::class => $file ]);
        };

        try {
            \touch($file, \time() - 60);
            $run();
            $run();
            $this->assertSame(1, $counting->calls);

            \touch($file, \time() - 3600);
            $run();
            $this->assertSame(2, $counting->calls);
        } finally {
            \unlink($file);
        }
    }
}
