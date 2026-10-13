<?php

namespace Ekwi\ComposerAttributeCollector;

use Closure;

/**
 * @internal
 */
class MemoizeClassMapFilter
{
    private const KEY = 'filtered';

    /**
     * @var array<non-empty-string, array{ int, bool }>
     *     Where _key_ is a pathname and _value_ its mtime as of filtering, and whether it was kept.
     */
    private array $state;

    public function __construct(
        private Datastore $datastore,
        private Logger $io,
    ) {
        /** @phpstan-ignore-next-line */
        $this->state = $this->datastore->get(self::KEY);
    }

    /**
     * @param array<class-string, non-empty-string> $classMap
     *     Where _key_ is a class and _value_ its pathname.
     * @param Closure(class-string, non-empty-string): bool $filter
     *
     * @return array<class-string, non-empty-string>
     */
    public function filter(array $classMap, Closure $filter): array
    {
        $filtered = [];
        $paths = [];

        foreach ($classMap as $class => $pathname) {
            $paths[$pathname] = true;
            [ $previousMtime, $keep ] = $this->state[$pathname] ?? [ null, false ];

            $mtime = \filemtime($pathname);

            \assert(\is_int($mtime));

            // Equality, not recency: an archive extracted in place can restore an older mtime.
            if ($mtime !== $previousMtime) {
                if ($previousMtime !== null) {
                    $this->io->debug("Refresh filtered file '$pathname' (modified)");
                } else {
                    $this->io->debug("Filter '$pathname'");
                }

                $keep = $filter($class, $pathname);
                $this->state[$pathname] = [ $mtime, $keep ];
            }

            if ($keep) {
                $filtered[$class] = $pathname;
            }
        }

        /**
         * Paths might have been removed, we need to filter entries according to the paths found.
         */
        $this->state = \array_filter(
            $this->state,
            static fn(string $k): bool => $paths[$k] ?? false,
            \ARRAY_FILTER_USE_KEY
        );

        $this->datastore->set(self::KEY, $this->state);

        return $filtered;
    }
}
