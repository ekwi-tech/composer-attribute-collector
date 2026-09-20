<?php

namespace Ekwi\ComposerAttributeCollector;

/**
 * Whether an include path can be handed to the class map generator.
 *
 * The prediction duplicates a contract that lives in two dependencies—{@link
 * \Composer\ClassMapGenerator\ClassMapGenerator::scanPaths()} throws on a path that is neither a file nor a
 * directory, and hands a wildcard path to Symfony's Finder, which throws when the pattern matches no
 * *directory*. `symfony/finder` is not even declared here; it arrives through `composer/class-map-generator`.
 *
 * Nothing upstream guarantees those conditions across versions, so `IncludePathTest` pins them against the
 * real generator: it fails the day a dependency stops agreeing with this class.
 *
 * @internal
 */
final class IncludePath
{
    /**
     * @return string|null
     *     Why the path cannot be scanned, or `null` when it can.
     */
    public static function unscannableReason(string $path): ?string
    {
        if (\is_file($path) || \is_dir($path)) {
            return null;
        }

        if (!\str_contains($path, '*')) {
            return 'it is neither a file nor a directory';
        }

        // The flags are those Finder uses to resolve the pattern. A wildcard matching only files, such as
        // `src/*.php`, matches no directory and has never been scannable.
        $flags = (\defined('GLOB_BRACE') ? \GLOB_BRACE : 0) | \GLOB_ONLYDIR | \GLOB_NOSORT;

        return \glob($path, $flags) ? null : 'it matches no directory';
    }
}
