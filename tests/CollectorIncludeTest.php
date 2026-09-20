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

    private static function generated(): string
    {
        self::assertFileExists(self::ATTRIBUTES_FILE);

        return (string) \file_get_contents(self::ATTRIBUTES_FILE);
    }

    /**
     * @param non-empty-string[] $include
     */
    private static function dump(array $include, FakeLogger $log): void
    {
        $config = new Config(
            vendorDir: __DIR__ . '/sandbox',
            attributesFile: self::ATTRIBUTES_FILE,
            include: $include,
            exclude: [],
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
        \assert(\is_string($cwd) && $cwd !== '');

        return $cwd;
    }
}
