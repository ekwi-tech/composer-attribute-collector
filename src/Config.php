<?php

namespace Ekwi\ComposerAttributeCollector;

use Composer\Factory;
use Composer\Package\PackageInterface;
use Composer\PartialComposer;
use Composer\Util\Platform;
use InvalidArgumentException;
use RuntimeException;

/**
 * @readonly
 * @internal
 */
final class Config
{
    public const EXTRA = 'composer-attribute-collector';
    public const EXTRA_INCLUDE = 'include';
    public const EXTRA_EXCLUDE = 'exclude';
    public const EXTRA_EXPOSE = 'expose';
    public const ENV_USE_CACHE = 'COMPOSER_ATTRIBUTE_COLLECTOR_USE_CACHE';
    public const FILENAME = 'attributes.php';

    /**
     * If a path starts with this placeholder, it is replaced with the absolute path to the vendor directory.
     */
    public const VENDOR_PLACEHOLDER = '{vendor}';

    public static function from(PartialComposer $composer, bool $isDebug = false, bool $isDevMode = true): self
    {
        $vendorDir = self::resolveVendorDir($composer);
        $composerFile = Factory::getComposerFile();
        $rootDir = \realpath(\dirname($composerFile));

        if (!$rootDir) {
            throw new RuntimeException("Unable to determine root directory");
        }

        $rootDir .= \DIRECTORY_SEPARATOR;
        $attributesFile = $vendorDir . \DIRECTORY_SEPARATOR . self::FILENAME;

        $package = $composer->getPackage();
        /** @var array{ include?: non-empty-string[], exclude?: non-empty-string[] } $extra */
        $extra = $package->getExtra()[self::EXTRA] ?? [];

        $include = self::expandPaths(
            $extra[self::EXTRA_INCLUDE] ?? self::resolveInclude($package, $attributesFile),
            $vendorDir,
            $rootDir,
        );
        $exclude = self::expandPaths($extra[self::EXTRA_EXCLUDE] ?? [], $vendorDir, $rootDir);

        [ $exposedInclude, $exposedExclude ] = self::resolveExposed($composer, $vendorDir, $isDevMode);
        $include = \array_values(\array_unique([ ...$include, ...$exposedInclude ]));
        $exclude = \array_values(\array_unique([ ...$exclude, ...$exposedExclude ]));

        $useCache = \filter_var(Platform::getEnv(self::ENV_USE_CACHE), \FILTER_VALIDATE_BOOL);

        return new self(
            $vendorDir,
            attributesFile: $attributesFile,
            include: $include,
            exclude: $exclude,
            useCache: $useCache,
            isDebug: $isDebug,
        );
    }

    /**
     * @return non-empty-string
     */
    public static function resolveVendorDir(PartialComposer $composer): string
    {
        $vendorDir = $composer->getConfig()->get('vendor-dir');

        if (!\is_string($vendorDir) || !$vendorDir) {
            throw new RuntimeException("Unable to determine vendor directory");
        }

        return $vendorDir;
    }

    /**
     * @param non-empty-string $attributesFile
     *
     * @return non-empty-string[]
     */
    public static function resolveInclude(PackageInterface $package, string $attributesFile): array
    {
        $include = [];

        foreach ($package->getAutoload() as $paths) {
            /** @var non-empty-string[] $paths */
            foreach ($paths as $path) {
                if (\realpath($path) === $attributesFile) {
                    continue;
                }

                $include[] = $path;
            }
        }

        return $include;
    }

    /**
     * Collects what installed packages expose under `extra.composer-attribute-collector.expose`.
     *
     * Paths are relative to the package's install path, without the `{vendor}` placeholder. Dev
     * packages are skipped outside dev mode, as the autoloader skips them.
     *
     * @return array{ non-empty-string[], non-empty-string[] }
     *     The include and exclude paths.
     */
    private static function resolveExposed(PartialComposer $composer, string $vendorDir, bool $isDevMode): array
    {
        $repository = $composer->getRepositoryManager()->getLocalRepository();
        $installationManager = $composer->getInstallationManager();
        $devPackageNames = $isDevMode ? [] : $repository->getDevPackageNames();
        // Installers realpath the vendor dir; put its raw form back so that `{vendor}` paths of the
        // root, and the paths of the scanned files, keep matching.
        $realVendorDir = \realpath($vendorDir) ?: $vendorDir;
        $include = [];
        $exclude = [];

        foreach ($repository->getCanonicalPackages() as $package) {
            if (\in_array($package->getName(), $devPackageNames, true)) {
                continue;
            }

            $expose = self::readExpose($package);

            if (!$expose) {
                continue;
            }

            // A metapackage has no install path, hence nothing to scan.
            $installPath = $installationManager->getInstallPath($package);

            if (!$installPath) {
                continue;
            }

            if (\str_starts_with($installPath, $realVendorDir . '/')) {
                $installPath = $vendorDir . \substr($installPath, \strlen($realVendorDir));
            }

            $packageDir = \rtrim($installPath, '/\\') . \DIRECTORY_SEPARATOR;
            $include = [ ...$include, ...self::prefixPaths($expose[self::EXTRA_INCLUDE], $packageDir) ];
            $exclude = [ ...$exclude, ...self::prefixPaths($expose[self::EXTRA_EXCLUDE], $packageDir) ];
        }

        return [ $include, $exclude ];
    }

    /**
     * A dependency's config is third-party input: a malformed one fails naming the package. Unknown
     * keys are ignored, so that a newer plugin version may add some.
     *
     * @return array{ include: non-empty-string[], exclude: non-empty-string[] }|null
     */
    private static function readExpose(PackageInterface $package): ?array
    {
        $extra = $package->getExtra()[self::EXTRA] ?? null;

        if (!\is_array($extra) || !\array_key_exists(self::EXTRA_EXPOSE, $extra)) {
            return null;
        }

        $expose = $extra[self::EXTRA_EXPOSE];
        $error = \sprintf(
            'Invalid "extra.%s.%s" in package %s, expected {"%s": [paths], "%s": [paths]}',
            self::EXTRA,
            self::EXTRA_EXPOSE,
            $package->getPrettyName(),
            self::EXTRA_INCLUDE,
            self::EXTRA_EXCLUDE,
        );
        $read = [ self::EXTRA_INCLUDE => [], self::EXTRA_EXCLUDE => [] ];

        if (!\is_array($expose) || ($expose && \array_is_list($expose))) {
            throw new InvalidArgumentException($error);
        }

        foreach (\array_keys($read) as $key) {
            $paths = $expose[$key] ?? [];

            if (!\is_array($paths) || !\array_is_list($paths)) {
                throw new InvalidArgumentException($error);
            }

            foreach ($paths as $path) {
                if (!\is_string($path) || $path === '') {
                    throw new InvalidArgumentException($error);
                }

                $read[$key][] = $path;
            }
        }

        return $read;
    }

    /**
     * @readonly
     * @var non-empty-string|null
     */
    public ?string $excludeRegExp;

    /**
     * @param non-empty-string $attributesFile
     *     Absolute path to the `attributes.php` file.
     * @param non-empty-string[] $include
     *     Paths that should be included in the attribute collection.
     * @param non-empty-string[] $exclude
     *     Paths that should be excluded from the attribute collection.
     * @param bool $useCache
     *     Whether a cache should be used during the process.
     * @param bool $isDebug
     *     Whether debug messages should be logged.
     */
    public function __construct(
        public string $vendorDir,
        public string $attributesFile,
        public array $include,
        public array $exclude,
        public bool $useCache,
        public bool $isDebug,
    ) {
        $this->excludeRegExp = \count($exclude) ? self::compileExclude($this->exclude) : null;
    }

    /**
     * @param non-empty-string[] $exclude
     *
     * @return non-empty-string
     */
    private static function compileExclude(array $exclude): string
    {
        $regexp = \implode('|', \array_map(fn (string $path) => \preg_quote($path), $exclude));

        return "($regexp)";
    }

    /**
     * @param non-empty-string[] $paths
     * @param non-empty-string $vendorDir
     * @param non-empty-string $rootDir
     *
     * @return non-empty-string[]
     */
    private static function expandPaths(array $paths, string $vendorDir, string $rootDir): array
    {
        if (\str_ends_with($vendorDir, \DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException("vendorDir must not end with a directory separator, given: $vendorDir");
        }

        if (!\str_ends_with($rootDir, \DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException("rootDir must end with a directory separator, given: $rootDir");
        }

        $expanded = [];

        foreach ($paths as $path) {
            $path = self::trimDotSlash($path);

            if (\str_starts_with($path, self::VENDOR_PLACEHOLDER)) {
                $path = $vendorDir . \substr($path, \strlen(self::VENDOR_PLACEHOLDER));
            } else {
                $path = $rootDir . $path;
            }

            $expanded[] = $path;
        }

        return $expanded;
    }

    /**
     * @param non-empty-string[] $paths
     * @param non-empty-string $dir
     *     Ends with a directory separator.
     *
     * @return non-empty-string[]
     */
    private static function prefixPaths(array $paths, string $dir): array
    {
        return \array_map(fn (string $path) => $dir . self::trimDotSlash($path), $paths);
    }

    private static function trimDotSlash(string $path): string
    {
        return \str_starts_with($path, "./") ? \substr($path, 2) : $path;
    }
}
