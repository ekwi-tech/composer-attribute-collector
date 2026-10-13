<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Ekwi\ComposerAttributeCollector\Datastore\FileDatastore;
use Ekwi\ComposerAttributeCollector\MemoizeClassMapGenerator;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class MemoizeClassMapGeneratorTest extends TestCase
{
    private const DIR = __DIR__ . '/sandbox-memoize-classmap/';

    protected function setUp(): void
    {
        parent::setUp();

        $remove = [
            self::DIR . 'a.php',
            self::DIR . 'a/b/c/b.php',
            self::DIR . 'a/b/c/e.php',
            self::DIR . 'a/d/d.php',
            self::DIR . 'a/b/c/notes.txt',
            self::DIR . 'a/b/c/.hidden.php',
            self::DIR . 'a/b/c/excluded.php',
        ];

        foreach ($remove as $filename) {
            if (\file_exists($filename)) {
                \unlink($filename);
            }
        }

        if (\is_dir(self::DIR . 'a/d')) {
            \rmdir(self::DIR . 'a/d');
        }

        foreach ([ 'a/dangling', 'a/linked' ] as $link) {
            if (\is_link(self::DIR . $link)) {
                \unlink(self::DIR . $link);
            }
        }
    }

    public function testMemoize(): void
    {
        $map = $this->map(self::DIR);
        $this->assertEmpty($map);

        // check changes in the directory are detected
        self::write(
            "a.php",
            <<<PHP
            <?php

            namespace App;

            #[\Acme\Attribute\Handler]
            class A {
            }
            PHP
        );

        $map = $this->map(self::DIR . 'a.php');
        $this->assertEquals([
            'App\A' => self::DIR . 'a.php',
        ], $map);

        // map again to test the code is not trying to iterate over the file like it's a directory
        $map = $this->map(self::DIR . 'a.php');
        $this->assertEquals([
            'App\A' => self::DIR . 'a.php',
        ], $map);

        // check changes in subdirectories are detected
        self::write(
            "a/b/c/b.php",
            <<<PHP
            <?php

            namespace App;

            #[\Acme\Attribute\Handler]
            class B {
            }
            PHP
        );

        $map = $this->map(self::DIR);
        $this->assertEquals([
            'App\A' => self::DIR . 'a.php',
            'App\B' => self::DIR . 'a/b/c/b.php',
        ], $map);
    }

    /**
     * Editing an exclusion touches no scanned file, so only the exclusion itself can tell the map is stale.
     */
    public function testAChangedExclusionRefreshesTheMap(): void
    {
        self::write('a.php', self::declare('A'));
        self::write('a/b/c/b.php', self::declare('B'));
        self::age();

        $this->assertEquals([
            'App\A' => self::DIR . 'a.php',
            'App\B' => self::DIR . 'a/b/c/b.php',
        ], $this->map(self::DIR));

        $this->assertEquals([
            'App\A' => self::DIR . 'a.php',
        ], $this->map(self::DIR, '(' . \preg_quote(self::DIR . 'a/b') . ')'));
    }

    /**
     * A wildcard has no mtime of its own, and a directory it newly matches may carry an old one, as an
     * extracted package does.
     */
    public function testAWildcardPicksUpADirectoryItNewlyMatches(): void
    {
        self::write('a/b/c/b.php', self::declare('B'));
        self::age();

        $this->assertEquals([
            'App\B' => self::DIR . 'a/b/c/b.php',
        ], $this->map(self::DIR . 'a/*'));

        \mkdir(self::DIR . 'a/d');
        self::write('a/d/d.php', self::declare('D'));
        self::age();

        $this->assertEquals([
            'App\B' => self::DIR . 'a/b/c/b.php',
            'App\D' => self::DIR . 'a/d/d.php',
        ], $this->map(self::DIR . 'a/*'));
    }

    /**
     * An archive extracted in place can rewrite a file under an mtime older than the last scan.
     */
    public function testAFileRewrittenWithAnOlderMtimeIsRescanned(): void
    {
        self::write('a/b/c/b.php', self::declare('B'));
        self::age();

        $this->assertEquals([
            'App\B' => self::DIR . 'a/b/c/b.php',
        ], $this->map(self::DIR . 'a'));

        \file_put_contents(self::DIR . 'a/b/c/b.php', self::declare('C'));
        \touch(self::DIR . 'a/b/c/b.php', \time() - 3600);
        self::age();

        $this->assertEquals([
            'App\C' => self::DIR . 'a/b/c/b.php',
        ], $this->map(self::DIR . 'a'));
    }

    /**
     * The counterpart of the tests above: an include whose exclusion and mtimes are unchanged keeps its map,
     * even a stale one, so the cache is not silently bypassed.
     */
    public function testAnUnchangedIncludeReusesItsMap(): void
    {
        \mkdir(self::DIR . 'a/d');
        self::write('a/b/c/b.php', self::declare('B'));
        self::write('a/d/d.php', self::declare('D'));
        self::age();

        $expected = [
            'App\B' => self::DIR . 'a/b/c/b.php',
            'App\D' => self::DIR . 'a/d/d.php',
        ];
        $this->assertEquals($expected, $this->map(self::DIR . 'a/*', '(\.never$)'));

        $mtime = \filemtime(self::DIR . 'a/b/c/b.php');
        \assert(\is_int($mtime));
        \file_put_contents(self::DIR . 'a/b/c/b.php', self::declare('C'));
        \touch(self::DIR . 'a/b/c/b.php', $mtime);

        $this->assertEquals($expected, $this->map(self::DIR . 'a/*', '(\.never$)'));
    }

    /**
     * The fingerprint watches what the generator scans, so a change it skips does not cost a rescan: a file
     * with another extension, a dot file, an excluded file.
     */
    public function testAChangeTheGeneratorSkipsKeepsTheMap(): void
    {
        $excluded = '(' . \preg_quote(self::DIR . 'a/b/c/excluded.php') . ')';
        self::write('a/b/c/b.php', self::declare('B'));
        self::age();

        $expected = [
            'App\B' => self::DIR . 'a/b/c/b.php',
        ];
        $this->assertEquals($expected, $this->map(self::DIR . 'a', $excluded));

        // Rewritten under the same mtime: only a rescan would tell.
        $mtime = \filemtime(self::DIR . 'a/b/c/b.php');
        $directoryMtime = \filemtime(self::DIR . 'a/b/c');
        \assert(\is_int($mtime) && \is_int($directoryMtime));
        \file_put_contents(self::DIR . 'a/b/c/b.php', self::declare('C'));
        \touch(self::DIR . 'a/b/c/b.php', $mtime);

        foreach ([ 'notes.txt', '.hidden.php', 'excluded.php' ] as $name) {
            \file_put_contents(self::DIR . "a/b/c/$name", self::declare('X'));
        }

        \touch(self::DIR . 'a/b/c', $directoryMtime);

        $this->assertEquals($expected, $this->map(self::DIR . 'a', $excluded));
    }

    /**
     * Without a fingerprint nothing can prove the map fresh, whatever the datastore holds.
     */
    public function testWithoutFingerprintsEveryScanIsFresh(): void
    {
        self::write('a/b/c/b.php', self::declare('B'));
        self::age();

        $this->assertEquals([
            'App\B' => self::DIR . 'a/b/c/b.php',
        ], $this->map(self::DIR . 'a', fingerprints: false));

        $mtime = \filemtime(self::DIR . 'a/b/c/b.php');
        \assert(\is_int($mtime));
        \file_put_contents(self::DIR . 'a/b/c/b.php', self::declare('C'));
        \touch(self::DIR . 'a/b/c/b.php', $mtime);

        $this->assertEquals([
            'App\C' => self::DIR . 'a/b/c/b.php',
        ], $this->map(self::DIR . 'a', fingerprints: false));
    }

    /**
     * The generator skips a dangling link by its extension, whatever its version; stat-ing it in the
     * fingerprint would throw instead. A dangling `.php` link is left out: some versions throw on it.
     */
    public function testADanglingSymlinkIsIgnored(): void
    {
        self::write('a/b/c/b.php', self::declare('B'));
        \symlink(self::DIR . 'nowhere', self::DIR . 'a/dangling');

        $this->assertEquals([
            'App\B' => self::DIR . 'a/b/c/b.php',
        ], $this->map(self::DIR . 'a'));
    }

    /**
     * A path repository symlinks the package: what changes behind the link must be seen.
     */
    public function testAChangeBehindASymlinkedDirectoryIsRescanned(): void
    {
        $target = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . \uniqid('memoize-classmap-target-');
        $link = self::DIR . 'a/linked';

        try {
            \mkdir($target);
            \file_put_contents("$target/l.php", self::declare('L'));
            \symlink($target, $link);
            self::age();

            $this->assertEquals([
                'App\L' => "$link/l.php",
            ], $this->map(self::DIR . 'a'));

            \file_put_contents("$target/l.php", self::declare('M'));
            \touch("$target/l.php", \time() - 3600);

            $this->assertEquals([
                'App\M' => "$link/l.php",
            ], $this->map(self::DIR . 'a'));
        } finally {
            if (\is_link($link)) {
                \unlink($link);
            }

            if (\is_file("$target/l.php")) {
                \unlink("$target/l.php");
            }

            if (\is_dir($target)) {
                \rmdir($target);
            }
        }
    }

    private static function declare(string $class): string
    {
        return "<?php\n\nnamespace App;\n\nclass $class {\n}\n";
    }

    /**
     * Moves every directory's mtime before the scan to come, so that only what is under test can refresh it.
     */
    private static function age(): void
    {
        $past = \time() - 60;
        \touch(self::DIR, $past);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::DIR, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                \touch($file->getPathname(), $past);
            }
        }
    }

    private static function write(string $name, string $data): void
    {
        \file_put_contents(self::DIR . $name, $data);

        // Because the modified time granularity is a second, we need the set the time to the next second,
        // so that we don't have to use sleep().
        \touch(self::DIR, \time() + 1);
    }

    /**
     * @param non-empty-string $path
     * @param non-empty-string|null $excluded
     *
     * @return array<class-string, non-empty-string>
     */
    private static function map(string $path, ?string $excluded = null, bool $fingerprints = true): array
    {
        $log = new FakeLogger();
        $generator = new MemoizeClassMapGenerator(
            new FileDatastore(get_cache_dir(), $log),
            $log,
            $fingerprints,
        );

        $generator->scanPaths($path, $excluded);

        return $generator->getMap();
    }
}
