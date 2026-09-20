<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Composer\ClassMapGenerator\ClassMapGenerator;
use Ekwi\ComposerAttributeCollector\IncludePath;
use PHPUnit\Framework\TestCase;
use Throwable;

final class IncludePathTest extends TestCase
{
    /**
     * @dataProvider providePath
     */
    public function testUnscannableReason(string $path, ?string $expected): void
    {
        $this->assertSame($expected, IncludePath::unscannableReason(self::root() . $path));
    }

    /**
     * The prediction is only worth what the dependencies still do. Run the real generator over the same paths
     * and require that it throws exactly when we said it would—this fails the day `composer/class-map-generator`
     * or `symfony/finder` changes the conditions we duplicate.
     *
     * @dataProvider providePath
     */
    public function testAgreesWithTheClassMapGenerator(string $path, ?string $expected): void
    {
        $path = self::root() . $path;
        $thrown = null;

        try {
            $generator = new ClassMapGenerator();
            $generator->scanPaths($path);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        if ($expected === null) {
            $this->assertNull(
                $thrown,
                "'$path' is predicted scannable but the generator threw: " . $thrown?->getMessage()
            );

            return;
        }

        $this->assertNotNull($thrown, "'$path' is predicted unscannable but the generator accepted it");
    }

    /**
     * @return array<string, array{ string, string|null }>
     */
    public static function providePath(): array
    {
        return [

            'a directory' => [ '/tests/Acme/PSR4/Presentation', null ],
            'a file' => [ '/tests/Acme/PSR4/CreateMenuHandler.php', null ],
            'a wildcard matching a directory' => [ '/tests/Acme/PSR4/Presentati*', null ],

            'a missing literal path' => [
                '/tests/Acme/ThereIsNoSuchDirectory',
                'it is neither a file nor a directory',
            ],
            'a wildcard matching nothing' => [
                '/tests/Acme/ThereIsNoSuch*/src',
                'it matches no directory',
            ],
            // Finder resolves a wildcard with GLOB_ONLYDIR, so a pattern matching only files is unscannable
            // however many files it matches. Surprising, and the reason the two warnings differ.
            'a wildcard matching only files' => [
                '/tests/Acme/PSR4/*.php',
                'it matches no directory',
            ],

        ];
    }

    /**
     * @return non-empty-string
     */
    private static function root(): string
    {
        $cwd = \getcwd();
        \assert(\is_string($cwd));

        return $cwd;
    }
}
