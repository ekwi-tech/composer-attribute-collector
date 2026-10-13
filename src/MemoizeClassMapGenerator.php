<?php

namespace Ekwi\ComposerAttributeCollector;

use Composer\ClassMapGenerator\ClassMapGenerator;
use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * @internal
 */
class MemoizeClassMapGenerator
{
    private const KEY = 'classmap';

    /**
     * Handed to the generator, so that the fingerprint watches the very files it scans.
     */
    private const EXTENSIONS = [ 'php', 'inc' ];

    /**
     * @var array<non-empty-string, array{ string|null, string|null, array<class-string, non-empty-string> }>
     *     Where _key_ is an include path and _value_ an array
     *     where `0` is the exclusion regexp and `1` the fingerprint of the scanned files, both as of
     *     that scan—a `null` fingerprint is never fresh—and `2` is an array
     *     where _key_ is a class and _value_ its pathname.
     */
    private array $state;

    /**
     * @var array<string, bool>
     */
    private array $paths;

    /**
     * @param bool $fingerprints
     *     Whether to fingerprint the scanned files, which only pays off when the datastore outlives the run.
     *     Without it, every include is rescanned.
     */
    public function __construct(
        private Datastore $datastore,
        private Logger $log,
        private bool $fingerprints = true,
    ) {
        /** @phpstan-ignore-next-line */
        $this->state = $this->datastore->get(self::KEY);
    }

    /**
     * @return array<class-string, non-empty-string>
     *     Where _key_ is a class and _value_ its path.
     */
    public function getMap(): array
    {
        /**
         * Paths might have been removed, we need to filter according to the paths provided during {@link scanPaths()}
         */
        $this->state = \array_filter(
            $this->state,
            fn(string $k): bool => $this->paths[$k] ?? false,
            \ARRAY_FILTER_USE_KEY
        );

        $this->datastore->set(self::KEY, $this->state);

        $maps = [];

        foreach ($this->state as [ , , $map ]) {
            $maps[] = $map;
        }

        return \array_merge(...$maps);
    }

    /**
     * Iterate over all files in the given directory searching for classes
     *
     * @param non-empty-string $path
     *     The path to search in.
     * @param non-empty-string|null $excluded
     *     Regex that matches file paths to be excluded from the classmap
     *
     * @throws RuntimeException When the path is neither an existing file nor directory
     */
    public function scanPaths(string $path, ?string $excluded = null): void
    {
        $this->paths[$path] = true;
        $fingerprint = $this->fingerprints ? self::fingerprint(IncludePath::resolve($path), $excluded) : null;

        if ($this->isStale($path, $excluded, $fingerprint)) {
            $inner = new ClassMapGenerator(self::EXTENSIONS);
            $inner->avoidDuplicateScans();
            $inner->scanPaths($path, $excluded);
            $map = $inner->getClassMap()->getMap();

            $this->state[$path] = [ $excluded, $fingerprint, $map ];
        }
    }

    private function isStale(string $path, ?string $excluded, ?string $fingerprint): bool
    {
        if ($fingerprint === null || !isset($this->state[$path])) {
            return true;
        }

        [ $previousExcluded, $previousFingerprint ] = $this->state[$path];

        // No mtime reflects an exclusion edited in a composer.json.
        if ($excluded !== $previousExcluded) {
            $this->log->debug("Refresh class map for path '$path' (exclusions changed)");

            return true;
        }

        if ($fingerprint !== $previousFingerprint) {
            $this->log->debug("Refresh class map for path '$path' (scanned files changed)");

            return true;
        }

        return false;
    }

    /**
     * The mtime of every file the generator scans and of the directories holding them, compared for equality
     * rather than recency: an archive extracted in place, or a package newly matched by a wildcard, can carry
     * mtimes older than the last scan.
     *
     * @param string[] $resolved
     */
    private static function fingerprint(array $resolved, ?string $excluded): string
    {
        $mtimes = [];

        // As Finder does by default: dot entries are ignored. A dangling link is neither a file nor a
        // directory, and stat-ing it would throw.
        $isScanned = static fn (SplFileInfo $file): bool => !\str_starts_with($file->getFilename(), '.')
            && ($excluded === null || !\preg_match($excluded, \strtr($file->getPathname(), '\\', '/')))
            && ($file->isDir() || ($file->isFile() && \in_array($file->getExtension(), self::EXTENSIONS, true)));

        foreach ($resolved as $path) {
            $mtimes[$path] = \filemtime($path);

            if (!\is_dir($path)) {
                continue;
            }

            // Symlinks are followed, as the class map generator follows them.
            $iterator = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator(
                        $path,
                        FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS,
                    ),
                    $isScanned,
                ),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                $mtimes[$file->getPathname()] = $file->getMTime();
            }
        }

        // Directory listings come in no guaranteed order.
        \ksort($mtimes);

        return \hash('xxh128', \serialize($mtimes));
    }
}
