<?php

namespace Ekwi\ComposerAttributeCollector;

use Ekwi\ComposerAttributeCollector\Datastore\FileDatastore;
use Ekwi\ComposerAttributeCollector\Datastore\RuntimeDatastore;
use Ekwi\ComposerAttributeCollector\Filter\ClassFilter;
use Ekwi\ComposerAttributeCollector\Filter\ContentFilter;
use RuntimeException;

/**
 * @internal
 * @readonly
 */
final class Collector
{
    public function __construct(
        private Config $config,
        private Logger $log,
    ) {
    }

    /**
     * Dumps the 'attributes.php' file.
     */
    public function dump(): void
    {
        $config = $this->config;
        $log =  $this->log;

        //
        // Scan the included paths
        //
        $start = \microtime(true);
        $datastore = $this->buildDefaultDatastore();
        $classMapGenerator = new MemoizeClassMapGenerator($datastore, $log);
        $skipped = 0;
        foreach ($config->include as $include) {
            $reason = IncludePath::unscannableReason($include);

            if ($reason !== null) {
                $log->warning("Generating attributes file: skipping include path '$include', $reason");
                $skipped++;

                continue;
            }

            $classMapGenerator->scanPaths($include, $config->excludeRegExp);
        }

        if ($skipped && $skipped === \count($config->include)) {
            // Rendering would overwrite a possibly good attributes file with an empty one, which fails at run
            // time instead of here. Every include being unresolvable is a misconfiguration, not a context.
            throw new RuntimeException(
                "Every include path was skipped, refusing to generate an empty $config->attributesFile"
            );
        }

        $classMap = $classMapGenerator->getMap();
        $elapsed = ElapsedTime::render($start);
        $log->debug("Generating attributes file: scanned paths in $elapsed");

        //
        // Filter the class map
        //
        $start = \microtime(true);
        $classMapFilter = new MemoizeClassMapFilter($datastore, $log);
        $filter = $this->buildFileFilter();
        $classMap = $classMapFilter->filter(
            $classMap,
            fn (string $class, string $filepath): bool => $filter->filter($filepath, $class, $log)
        );
        $elapsed = ElapsedTime::render($start);
        $log->debug("Generating attributes file: filtered class map in $elapsed");

        //
        // Collect attributes
        //
        $start = \microtime(true);
        $attributeCollector = new MemoizeAttributeCollector(new ClassAttributeCollector($log), $datastore, $log);
        $collection = $attributeCollector->collectAttributes($classMap);
        $elapsed = ElapsedTime::render($start);
        $log->debug("Generating attributes file: collected attributes in $elapsed");

        //
        // Render attributes
        //
        $start = \microtime(true);
        $code = $this->render($collection);
        \file_put_contents($config->attributesFile, $code);
        $elapsed = ElapsedTime::render($start);
        $log->debug("Generating attributes file: rendered code in $elapsed");
    }

    private function buildDefaultDatastore(): Datastore
    {
        if (!$this->config->useCache) {
            return new RuntimeDatastore();
        }

        $basePath = \getcwd() ?: throw new RuntimeException('Unable to locate base path');

        return new FileDatastore($basePath . \DIRECTORY_SEPARATOR . Plugin::CACHE_DIR, $this->log);
    }

    private function buildFileFilter(): Filter
    {
        return new Filter\Chain([
            new ContentFilter(),
            new ClassFilter()
        ]);
    }

    private function render(TransientCollection $collector): string
    {
        return TransientCollectionRenderer::render($collector);
    }
}
