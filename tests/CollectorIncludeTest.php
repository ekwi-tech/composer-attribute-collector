<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Ekwi\ComposerAttributeCollector\Collector;
use Ekwi\ComposerAttributeCollector\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CollectorIncludeTest extends TestCase
{
    private const ATTRIBUTES_FILE = __DIR__ . '/sandbox/include-attributes.php';

    protected function setUp(): void
    {
        parent::setUp();

        if (\file_exists(self::ATTRIBUTES_FILE)) {
            \unlink(self::ATTRIBUTES_FILE);
        }
    }

    /**
     * An unresolvable include path must not abort the dump, as long as another one still resolves.
     *
     * @dataProvider provideUnresolvableInclude
     */
    public function testUnresolvableIncludeIsSkippedWithAWarning(string $include, string $expectedReason): void
    {
        $include = self::cwd() . $include;
        $log = new FakeLogger();

        self::dump([ self::cwd() . '/tests/Acme/PSR4/Presentation', $include ], $log);

        $this->assertContains(
            "Generating attributes file: skipping include path '$include', $expectedReason",
            $log->warnings
        );
        $this->assertStringContainsString('Acme\\\\PSR4\\\\Presentation\\\\ArticleController', self::generated());
    }

    /**
     * @return array<string, array{ string, string }>
     */
    public static function provideUnresolvableInclude(): array
    {
        return [

            'a literal path' => [
                '/tests/Acme/ThereIsNoSuchDirectory',
                'it is neither a file nor a directory',
            ],

            // `glob()` is given GLOB_ONLYDIR, as Finder does, so a pattern matching only files—`*.php`—is
            // unscannable too, and has always been.
            'a wildcard path matching no directory' => [
                '/tests/Acme/ThereIsNoSuch*/src',
                'it matches no directory',
            ],

        ];
    }

    /**
     * @dataProvider provideResolvableInclude
     */
    public function testResolvableIncludeIsScanned(string $include, string $expectedTarget): void
    {
        $log = new FakeLogger();

        self::dump([ self::cwd() . $include ], $log);

        $this->assertEmpty($log->warnings, \implode("\n", $log->warnings));
        $this->assertStringContainsString($expectedTarget, self::generated());
    }

    /**
     * @return array<string, array{ string, string }>
     */
    public static function provideResolvableInclude(): array
    {
        return [

            'a directory' => [
                '/tests/Acme/PSR4/Presentation',
                'Acme\\\\PSR4\\\\Presentation\\\\ArticleController',
            ],
            'a file' => [
                '/tests/Acme/PSR4/CreateMenuHandler.php',
                'Acme\\\\PSR4\\\\CreateMenuHandler',
            ],
            'a wildcard path' => [
                '/tests/Acme/PSR4/Presentati*',
                'Acme\\\\PSR4\\\\Presentation\\\\ArticleController',
            ],

        ];
    }

    /**
     * Skipping every include would render an empty attributes file over a possibly good one.
     */
    public function testEveryIncludeSkippedIsFatal(): void
    {
        $log = new FakeLogger();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Every include path was skipped');

        try {
            self::dump([ self::cwd() . '/tests/Acme/ThereIsNoSuchDirectory' ], $log);
        } finally {
            $this->assertFileDoesNotExist(self::ATTRIBUTES_FILE);
        }
    }

    /**
     * The layout of a path repository: the package is symlinked into the vendor dir, and the root still scans
     * it through a wildcard. The exclusion the package exposes must reach those files all the same.
     */
    public function testAnExposedExclusionFiltersARootWildcardOverASymlinkedPackage(): void
    {
        $dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . \uniqid('collector-include-test-');
        $src = "$dir/package/src";
        $installed = "$dir/vendor/acme/package";
        $links = [ "$src/ActiveRecord", "$src/Presentation", $installed ];

        try {
            \mkdir($src, recursive: true);
            \mkdir(\dirname($installed), recursive: true);
            \symlink(self::cwd() . '/tests/Acme/PSR4/ActiveRecord', "$src/ActiveRecord");
            \symlink(self::cwd() . '/tests/Acme/PSR4/Presentation', "$src/Presentation");
            \symlink("$dir/package", $installed);

            // The exclusion as Config::from() leaves it, under the package's install path.
            self::dump([ "$dir/vendor/acme/*/src" ], new FakeLogger(), [ "$installed/src/Presentation" ]);
        } finally {
            foreach ($links as $link) {
                if (\is_link($link)) {
                    \unlink($link);
                }
            }

            foreach ([ $src, "$dir/package", \dirname($installed), "$dir/vendor", $dir ] as $directory) {
                if (\is_dir($directory)) {
                    \rmdir($directory);
                }
            }
        }

        $this->assertStringContainsString('Acme\\\\PSR4\\\\ActiveRecord\\\\Article', self::generated());
        $this->assertStringNotContainsString('Acme\\\\PSR4\\\\Presentation', self::generated());
    }

    private static function generated(): string
    {
        self::assertFileExists(self::ATTRIBUTES_FILE);

        return (string) \file_get_contents(self::ATTRIBUTES_FILE);
    }

    /**
     * @param non-empty-string[] $include
     * @param non-empty-string[] $exclude
     */
    private static function dump(array $include, FakeLogger $log, array $exclude = []): void
    {
        $config = new Config(
            vendorDir: __DIR__ . '/sandbox',
            attributesFile: self::ATTRIBUTES_FILE,
            include: $include,
            exclude: $exclude,
            useCache: false,
            isDebug: false,
        );

        (new Collector($config, $log))->dump();
    }

    /**
     * @return non-empty-string
     */
    private static function cwd(): string
    {
        $cwd = \getcwd();
        \assert(\is_string($cwd));

        return $cwd;
    }
}
