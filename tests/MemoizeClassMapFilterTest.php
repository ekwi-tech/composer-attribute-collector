<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Acme\PSR4\Presentation\ArticleController;
use Ekwi\ComposerAttributeCollector\Datastore\RuntimeDatastore;
use Ekwi\ComposerAttributeCollector\MemoizeClassMapFilter;
use PHPUnit\Framework\TestCase;

final class MemoizeClassMapFilterTest extends TestCase
{
    /**
     * An archive extracted in place can restore an mtime older than the last run, which is a change too.
     */
    public function testAFileIsFilteredAgainWhenItsMtimeChangesEitherWay(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'memoize-filter-');
        \assert(\is_string($file));
        $datastore = new RuntimeDatastore();
        $filter = new class {
            public int $calls = 0;

            public function __invoke(): bool
            {
                $this->calls++;

                return true;
            }
        };
        $run = function () use ($datastore, $file, $filter): void {
            (new MemoizeClassMapFilter($datastore, new FakeLogger()))->filter(
                [ ArticleController::class => $file ],
                $filter(...),
            );
        };

        try {
            \touch($file, \time() - 60);
            $run();
            $run();
            $this->assertSame(1, $filter->calls);

            \touch($file, \time() - 3600);
            $run();
            $this->assertSame(2, $filter->calls);
        } finally {
            \unlink($file);
        }
    }
}
