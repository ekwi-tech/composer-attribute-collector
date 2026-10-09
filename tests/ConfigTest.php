<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Composer\Installer\InstallationManager;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\PartialComposer;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\RepositoryManager;
use Composer\Util\Platform;
use Ekwi\ComposerAttributeCollector\Config;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    public function testFrom(): void
    {
        $extra = [
            Config::EXTRA => [
                Config::EXTRA_INCLUDE => [
                    'tests',
                    '{vendor}/vendor1/package1',
                ],
                Config::EXTRA_EXCLUDE => [
                    'tests/Acme/PSR4/IncompatibleSignature.php',
                    '{vendor}/vendor1/package1/file.php',
                ],
            ]
        ];

        $package = $this->createMock(RootPackageInterface::class);
        $package
            ->method('getExtra')
            ->willReturn($extra);

        $cwd = Platform::getCwd();
        $composer = $this->makeComposer($package);

        $expected = new Config(
            vendorDir: "$cwd/vendor",
            attributesFile: "$cwd/vendor/attributes.php",
            include: [
                "$cwd/tests",
                "$cwd/vendor/vendor1/package1",
            ],
            exclude: [
                "$cwd/tests/Acme/PSR4/IncompatibleSignature.php",
                "$cwd/vendor/vendor1/package1/file.php",
            ],
            useCache: false,
            isDebug: false,
        );

        $actual = Config::from($composer);

        $this->assertEquals($expected, $actual);
    }

    public function testResolveIncludeFromAutoload(): void
    {
        $package = $this->createMock(RootPackageInterface::class);
        $package
            ->method('getExtra')
            ->willReturn([]);
        $package
            ->expects($this->once())
            ->method('getAutoload')
            ->willReturn([
                'classmap' => [
                    'src/classmap',
                    'src/bootstrap.php',
                ],
                'psr-0' => [
                    'Acme/PSR4' => './src/psr-0',
                ],
                'psr-4' => [
                    'Acme/PSR4' => 'src/psr-4',
                ],
                'files' => [
                    './src/files'
                ]
            ]);

        $cwd = Platform::getCwd();
        $composer = $this->makeComposer($package);

        $expected = new Config(
            vendorDir: "$cwd/vendor",
            attributesFile: "$cwd/vendor/attributes.php",
            include: [
                "$cwd/src/classmap",
                "$cwd/src/bootstrap.php",
                "$cwd/src/psr-0",
                "$cwd/src/psr-4",
                "$cwd/src/files",
            ],
            exclude: [],
            useCache: false,
            isDebug: false,
        );

        $actual = Config::from($composer);

        $this->assertEquals($expected, $actual);
    }

    public function testFromFailsOnMissingVendorDir(): void
    {
        $config = $this->createMock(\Composer\Config::class);
        $config
            ->method('get')
            ->with('vendor-dir')
            ->willReturn("");

        $composer = new PartialComposer();
        $composer->setConfig($config);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to determine vendor directory");

        Config::from($composer);
    }

    public function testFromMergesWhatDependenciesExpose(): void
    {
        $package = $this->makeRootPackage([
            Config::EXTRA_INCLUDE => [
                'src',
                '{vendor}/vendor1/package1/src',
            ],
            Config::EXTRA_EXCLUDE => [
                '{vendor}/vendor1/package2/src/Legacy.php',
            ],
        ]);

        $exposing = $this->makePackage('vendor1/package1', [
            Config::EXTRA_INCLUDE => [ 'tests' ],
            Config::EXTRA_EXPOSE => [
                Config::EXTRA_INCLUDE => [ './src', 'lib' ],
                Config::EXTRA_EXCLUDE => [ 'lib/Internal.php' ],
            ],
        ]);
        $excluding = $this->makePackage('vendor1/package2', [
            Config::EXTRA_EXPOSE => [
                Config::EXTRA_INCLUDE => [ 'src' ],
                Config::EXTRA_EXCLUDE => [ 'src/Legacy.php' ],
            ],
        ]);
        $silent = $this->makePackage('vendor1/package3', [ Config::EXTRA_INCLUDE => [ 'src' ] ]);
        $meta = $this->makePackage('vendor1/meta', [ Config::EXTRA_EXPOSE => [ Config::EXTRA_INCLUDE => [ 'src' ] ] ]);
        $meta->setType('metapackage');

        $cwd = Platform::getCwd();
        $actual = Config::from($this->makeComposer($package, [ $exposing, $excluding, $silent, $meta ]));

        $this->assertEquals([
            "$cwd/src",
            "$cwd/vendor/vendor1/package1/src",
            "$cwd/vendor/vendor1/package1/lib",
            "$cwd/vendor/vendor1/package2/src",
        ], $actual->include);
        $this->assertEquals([
            "$cwd/vendor/vendor1/package2/src/Legacy.php",
            "$cwd/vendor/vendor1/package1/lib/Internal.php",
        ], $actual->exclude);
    }

    public function testFromSkipsDevPackagesOutsideDevMode(): void
    {
        $package = $this->makeRootPackage([ Config::EXTRA_INCLUDE => [ 'src' ] ]);
        $dev = $this->makePackage('vendor2/dev', [ Config::EXTRA_EXPOSE => [ Config::EXTRA_INCLUDE => [ 'src' ] ] ]);
        $malformedDev = $this->makePackage('vendor2/malformed', [ Config::EXTRA_EXPOSE => 'src' ]);
        $cwd = Platform::getCwd();

        // Skipped before being read: a dev package cannot fail a `--no-dev` dump.
        $composer = $this->makeComposer($package, [ $dev, $malformedDev ], [ 'vendor2/dev', 'vendor2/malformed' ]);
        $this->assertEquals([ "$cwd/src" ], Config::from($composer, isDevMode: false)->include);

        $dev = $this->makePackage('vendor2/dev', [ Config::EXTRA_EXPOSE => [ Config::EXTRA_INCLUDE => [ 'src' ] ] ]);
        $composer = $this->makeComposer($package, [ $dev ], [ 'vendor2/dev' ]);
        $this->assertEquals(
            [ "$cwd/src", "$cwd/vendor/vendor2/dev/src" ],
            Config::from($composer, isDevMode: true)->include,
        );
    }

    public function testFromMapsInstallPathsBackOntoTheRawVendorDir(): void
    {
        $dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . \uniqid('config-test-');
        \mkdir("$dir/real-vendor", recursive: true);
        \symlink("$dir/real-vendor", "$dir/vendor");

        try {
            $package = $this->makeRootPackage([
                Config::EXTRA_INCLUDE => [ 'src' ],
                Config::EXTRA_EXCLUDE => [ '{vendor}/vendor1/package1/src/Legacy.php' ],
            ]);
            $exposing = $this->makePackage('vendor1/package1', [
                Config::EXTRA_EXPOSE => [ Config::EXTRA_INCLUDE => [ 'src' ] ],
            ]);

            $actual = Config::from($this->makeComposer($package, [ $exposing ], vendorDir: "$dir/vendor"));

            $this->assertEquals("$dir/vendor", $actual->vendorDir);
            $this->assertEquals("$dir/vendor/vendor1/package1/src", $actual->include[1]);
            $this->assertEquals([ "$dir/vendor/vendor1/package1/src/Legacy.php" ], $actual->exclude);
        } finally {
            \unlink("$dir/vendor");
            \rmdir("$dir/real-vendor");
            \rmdir($dir);
        }
    }

    /**
     * @dataProvider provideMalformedExpose
     */
    public function testFromRejectsAMalformedExpose(mixed $expose): void
    {
        $package = $this->makeRootPackage([ Config::EXTRA_INCLUDE => [ 'src' ] ]);
        $faulty = new Package('vendor1/faulty', '1.0.0.0', '1.0.0');
        $faulty->setExtra([ Config::EXTRA => [ Config::EXTRA_EXPOSE => $expose ] ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('vendor1/faulty');

        Config::from($this->makeComposer($package, [ $faulty ]));
    }

    /**
     * @return array<string, array{ mixed }>
     */
    public static function provideMalformedExpose(): array
    {
        return [
            'a string' => [ 'src' ],
            'a list' => [ [ 'src' ] ],
            'paths as a string' => [ [ Config::EXTRA_INCLUDE => 'src' ] ],
            'an empty path' => [ [ Config::EXTRA_INCLUDE => [ '' ] ] ],
            'a non-string path' => [ [ Config::EXTRA_EXCLUDE => [ 42 ] ] ],
        ];
    }

    public function testFromAcceptsAnEmptyExposeAndIgnoresUnknownKeys(): void
    {
        $package = $this->makeRootPackage([ Config::EXTRA_INCLUDE => [ 'src' ] ]);
        $empty = $this->makePackage('vendor1/empty', [ Config::EXTRA_EXPOSE => [] ]);
        $newer = $this->makePackage('vendor1/newer', [
            Config::EXTRA_EXPOSE => [ Config::EXTRA_INCLUDE => [ 'src' ], 'future' => true ],
        ]);
        $cwd = Platform::getCwd();

        $actual = Config::from($this->makeComposer($package, [ $empty, $newer ]));

        $this->assertEquals([ "$cwd/src", "$cwd/vendor/vendor1/newer/src" ], $actual->include);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function makeRootPackage(array $extra): RootPackageInterface
    {
        $package = $this->createMock(RootPackageInterface::class);
        $package
            ->method('getExtra')
            ->willReturn([ Config::EXTRA => $extra ]);

        return $package;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function makePackage(string $name, array $extra): Package
    {
        $package = new Package($name, '1.0.0.0', '1.0.0');
        $package->setExtra([ Config::EXTRA => $extra ]);

        return $package;
    }

    /**
     * @param PackageInterface[] $installed
     * @param string[] $devPackageNames
     */
    private function makeComposer(
        RootPackageInterface $package,
        array $installed = [],
        array $devPackageNames = [],
        ?string $vendorDir = null,
    ): PartialComposer {
        $vendorDir ??= Platform::getCwd() . '/vendor';

        $config = $this->createMock(\Composer\Config::class);
        $config
            ->method('get')
            ->with('vendor-dir')
            ->willReturn($vendorDir);

        $localRepository = new InstalledArrayRepository($installed);
        $localRepository->setDevPackageNames($devPackageNames);

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->method('getLocalRepository')
            ->willReturn($localRepository);

        // Mimics LibraryInstaller, which realpaths the vendor dir, and an installer leaving a
        // trailing separator.
        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager
            ->method('getInstallPath')
            ->willReturnCallback(fn (PackageInterface $p) => match (true) {
                $p->getType() === 'metapackage' => null,
                $p->getName() === 'vendor1/package2' => \realpath($vendorDir) . "/{$p->getName()}/",
                default => \realpath($vendorDir) . "/{$p->getName()}",
            });

        $composer = new PartialComposer();
        $composer->setConfig($config);
        $composer->setPackage($package);
        $composer->setRepositoryManager($repositoryManager);
        $composer->setInstallationManager($installationManager);

        return $composer;
    }
}
