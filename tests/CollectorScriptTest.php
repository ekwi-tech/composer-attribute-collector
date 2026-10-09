<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Ekwi\ComposerAttributeCollector\Config;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class CollectorScriptTest extends TestCase
{
    /**
     * Installed, the script sits in `vendor/ekwi-tech/composer-attribute-collector`: neither its own
     * directory nor the working directory is the vendor dir's parent when `vendor-dir` is customized.
     */
    public function testLoadsTheAutoloaderOfTheConfiguredVendorDir(): void
    {
        $root = \dirname(__DIR__);
        $dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . \uniqid('collector-script-test-');
        \mkdir("$dir/src", recursive: true);

        try {
            \copy("$root/collector.php", "$dir/collector.php");
            \copy("$root/src/Config.php", "$dir/src/Config.php");

            $config = new Config(
                vendorDir: "$root/vendor",
                attributesFile: "$dir/attributes.php",
                include: [ "$root/tests/Acme81" ],
                exclude: [],
                useCache: false,
                isDebug: false,
            );
            \file_put_contents("$dir/config", \serialize($config));

            $process = new Process([ \PHP_BINARY, "$dir/collector.php", "$dir/config" ], cwd: $dir);
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput() . $process->getOutput());
            $this->assertStringContainsString('Acme81', (string) \file_get_contents("$dir/attributes.php"));
        } finally {
            // Silenced, so that a cleanup failure never hides the assertion failure.
            foreach ([ ...(\glob("$dir/src/*") ?: []), ...(\glob("$dir/*") ?: []) ] as $file) {
                @\unlink($file);
            }
            @\rmdir("$dir/src");
            @\rmdir($dir);
        }
    }
}
