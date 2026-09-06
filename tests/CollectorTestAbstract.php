<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\ComposerAttributeCollector;

use Acme\Attribute\ActiveRecord\Boolean;
use Acme\Attribute\ActiveRecord\Id;
use Acme\Attribute\ActiveRecord\Index;
use Acme\Attribute\ActiveRecord\SchemaAttribute;
use Acme\Attribute\ActiveRecord\Serial;
use Acme\Attribute\ActiveRecord\Text;
use Acme\Attribute\ActiveRecord\Varchar;
use Acme\Attribute\AutowiredService;
use Acme\Attribute\Get;
use Acme\Attribute\Handler;
use Acme\Attribute\Permission;
use Acme\Attribute\Resource;
use Acme\Attribute\Route;
use Acme\Attribute\Routing\UrlGetter;
use Acme\Attribute\Subscribe;
use Acme\PSR4\Presentation\ArticleController;
use Acme81\Attribute\ParameterA;
use Acme81\Attribute\ParameterB;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\Config;
use olvlvl\ComposerAttributeCollector\TargetClass;
use olvlvl\ComposerAttributeCollector\TargetMethod;
use olvlvl\ComposerAttributeCollector\TargetParameter;
use olvlvl\ComposerAttributeCollector\TargetProperty;
use PhpParser\Node\Param;
use PHPUnit\Framework\TestCase;
use ReflectionException;

use function getcwd;
use function is_string;
use function str_contains;
use function usort;

use const PHP_VERSION_ID;

abstract class CollectorTestAbstract extends TestCase
{
    /**
     * @var array<class-string, bool>
     */
    private static array $initialized = [];

    /**
     * @throws ReflectionException
     */
    final protected function setUp(): void
    {
        parent::setUp();

        if (self::$initialized[get_called_class()] ?? false) {
            return;
        }

        $config = self::makeConfig();

        static::dump($config);

        $filepath = $config->attributesFile;
        $this->assertFileExists($filepath);
        require $filepath;

        self::$initialized[get_called_class()] = true;
    }

    abstract protected static function dump(Config $config): void;

    private static function makeConfig(): Config
    {
        $cwd = getcwd();
        assert(is_string($cwd));
        $vendorDir = __DIR__ . '/sandbox';
        $filepath = "$vendorDir/attributes.php";
        $exclude = [
            "$cwd/tests/Acme/PSR4/IncompatibleSignature.php",
        ];

        if (PHP_VERSION_ID < 80100) {
            $exclude[] = "$cwd/tests/Acme81";
        }

        return new Config(
            vendorDir: $vendorDir,
            attributesFile: $filepath,
            include: [
                "$cwd/tests",
            ],
            exclude: $exclude,
            useCache: false,
            isDebug: false,
        );
    }

    /**
     * @dataProvider provideTargetClasses
     *
     * @param class-string $attribute
     * @param array<array{ class-string, class-string }> $expected
     */
    public function testTargetClasses(string $attribute, array $expected): void
    {
        $actual = Attributes::findTargetClasses($attribute);

        $this->assertEquals($expected, $this->collectClasses($actual));
    }

    /**
     * @return array<array{ class-string, array<array{ class-string, class-string }> }>
     */
    public static function provideTargetClasses(): array
    {
        return [

            [
                Permission::class,
                [
                    [ Permission::class, \Acme\PSR4\CreateMenu::class ],
                    [ Permission::class, \Acme\PSR4\CreateMenu::class ],
                    [ Permission::class, \Acme\PSR4\DeleteMenu::class ],
                    [ Permission::class, \Acme\PSR4\DeleteMenu::class ],
                ],
            ],
            [
                Handler::class,
                [
                    [ Handler::class, \Acme\PSR4\CreateMenuHandler::class ],
                    [ Handler::class, \Acme\PSR4\DeleteMenuHandler::class ],
                ],
            ],
            [
                Index::class,
                [
                    [ Index::class, \Acme\PSR4\ActiveRecord\Article::class ],
                ],
            ],
            [
                AutowiredService::class,
                [
                    [
                        AutowiredService::class,
                        \Acme\PSR4\SignatureMapProvider::class,
                    ],
                ],
            ],

        ];
    }

    /**
     * @dataProvider provideTargetMethods
     *
     * @param class-string $attribute
     * @param array<array{ class-string, callable-string }> $expected
     */
    public function testTargetMethods(string $attribute, array $expected): void
    {
        $actual = Attributes::findTargetMethods($attribute);

        $this->assertEquals($expected, $this->collectMethods($actual));
    }

    /**
     * @return array<array{ class-string, array<array{ class-string, callable-string }> }>
     */
    public static function provideTargetMethods(): array
    {
        return [

            [
                Route::class,
                [
                    [
                        Route::class,
                        'Acme\PSR4\Presentation\ArticleController::aMethod',
                    ],
                    [
                        Route::class,
                        'Acme\PSR4\Presentation\ArticleController::list',
                    ],
                    [
                        Route::class,
                        'Acme\PSR4\Presentation\ArticleController::show',
                    ],
                ],
            ],
            [
                Get::class,
                [
                    [ Get::class, 'Acme\Presentation\FileController::list' ],
                    [ Get::class, 'Acme\Presentation\FileController::show' ],
                    [ Get::class, 'Acme\Presentation\ImageController::list' ],
                    [ Get::class, 'Acme\Presentation\ImageController::show' ],
                ],
            ],
            [
                Subscribe::class,
                [
                    [ Subscribe::class, 'Acme\PSR4\SubscriberA::onEventA' ],
                    [ Subscribe::class, 'Acme\PSR4\SubscriberB::onEventA' ],
                ],
            ],
            [
                UrlGetter::class,
                [
                    [ UrlGetter::class, 'Acme\PSR4\InheritedAttributeSample::get_url' ],
                    [ UrlGetter::class, 'Acme\PSR4\Routing\UrlTrait::get_url' ],
                ],
            ],

        ];
    }

    /**
     * @dataProvider provideTargetParameters
     *
     * @param class-string $attribute
     * @param array<array{ class-string, callable-string }> $expected
     */
    public function testTargetParameters(string $attribute, array $expected): void
    {
        $actual = Attributes::findTargetParameters($attribute);

        $this->assertEquals($expected, $this->collectParameters($actual));
    }

    /**
     * @return array<array{ class-string, array<array{ class-string, callable-string }> }>
     */
    public static function provideTargetParameters(): array
    {
        return [

            [
                ParameterA::class,
                [
                    [
                        ParameterA::class,
                        'Acme\PSR4\Presentation\ArticleController::aMethod(myParameter)',
                    ],
                    [
                        ParameterA::class,
                        'Acme\PSR4\Presentation\ArticleController::aMethod(yetAnotherParameter)',
                    ],
                ],
            ],
            [
                ParameterB::class,
                [
                    [
                        ParameterB::class,
                        'Acme\PSR4\Presentation\ArticleController::aMethod(anotherParameter)',
                    ],
                ],
            ],

        ];
    }

    /**
     * @dataProvider provideTargetProperties
     *
     * @param class-string $attribute
     * @param array<array{ class-string, string }> $expected
     */
    public function testTargetProperties(string $attribute, array $expected): void
    {
        $actual = Attributes::findTargetProperties($attribute);

        $this->assertEquals($expected, $this->collectProperties($actual));
    }

    /**
     * @return array<array{ class-string, array<array{ class-string, string }> }>
     */
    public static function provideTargetProperties(): array
    {
        return [

            [
                Serial::class,
                [
                    [ Serial::class, 'Acme\PSR4\ActiveRecord\Article::id' ],
                ],
            ],

            [
                Varchar::class,
                [
                    [ Varchar::class, 'Acme\PSR4\ActiveRecord\Article::slug' ],
                    [ Varchar::class, 'Acme\PSR4\ActiveRecord\Article::title' ],
                ],
            ],

            [
                Text::class,
                [
                    [ Text::class, 'Acme\PSR4\ActiveRecord\Article::body' ],
                ],
            ],

        ];
    }

    public function testFilterTargetClasses(): void
    {
        $actual = Attributes::filterTargetClasses(
            fn($attribute, $class) => str_contains($class, 'Menu'),
        );

        $this->assertEquals([
            [ Permission::class, \Acme\PSR4\CreateMenu::class ],
            [ Permission::class, \Acme\PSR4\CreateMenu::class ],
            [ Handler::class, \Acme\PSR4\CreateMenuHandler::class ],
            [ Permission::class, \Acme\PSR4\DeleteMenu::class ],
            [ Permission::class, \Acme\PSR4\DeleteMenu::class ],
            [ Handler::class, \Acme\PSR4\DeleteMenuHandler::class ],
        ], $this->collectClasses($actual));
    }

    public function testFilterTargetMethods(): void
    {
        $actual = Attributes::filterTargetMethods(
            Attributes::predicateForAttributeInstanceOf(Route::class),
        );

        $this->assertEquals([
            [
                Route::class,
                'Acme\PSR4\Presentation\ArticleController::aMethod',
            ],
            [ Route::class, 'Acme\PSR4\Presentation\ArticleController::list' ],
            [ Route::class, 'Acme\PSR4\Presentation\ArticleController::show' ],
            [ Get::class, 'Acme\Presentation\FileController::list' ],
            [ Get::class, 'Acme\Presentation\FileController::show' ],
            [ Get::class, 'Acme\Presentation\ImageController::list' ],
            [ Get::class, 'Acme\Presentation\ImageController::show' ],
        ], $this->collectMethods($actual));
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testFilterTargetMethods81(): void
    {
        $expected = [
            new TargetMethod(
                \Acme81\Attribute\Route::class,
                \Acme81\PSR4\Presentation\ArticleController::class,
                'show',
            ),
            new TargetMethod(
                \Acme81\Attribute\Get::class,
                \Acme81\PSR4\Presentation\ArticleController::class,
                'list',
            ),
            new TargetMethod(
                \Acme81\Attribute\Post::class,
                \Acme81\PSR4\Presentation\ArticleController::class,
                'new',
            ),
        ];

        $actual = Attributes::filterTargetMethods(
            Attributes::predicateForAttributeInstanceOf(\Acme81\Attribute\Route::class),
        );

        $this->assertEquals($expected, $actual);
    }

    public function testFilterTargetParameters(): void
    {
        $actual = Attributes::filterTargetParameters(
            Attributes::predicateForAttributeInstanceOf(ParameterA::class),
        );

        $this->assertEquals([
            [ ParameterA::class, 'Acme\PSR4\Presentation\ArticleController::aMethod(myParameter)' ],
            [
                ParameterA::class,
                'Acme\PSR4\Presentation\ArticleController::aMethod(yetAnotherParameter)',
            ],
        ], $this->collectParameters($actual));
    }

    public function testFilterTargetProperties(): void
    {
        $actual = Attributes::filterTargetProperties(
            Attributes::predicateForAttributeInstanceOf(SchemaAttribute::class),
        );

        $this->assertEquals([
            [ Boolean::class, 'Acme\PSR4\ActiveRecord\Article::active' ],
            [ Text::class, 'Acme\PSR4\ActiveRecord\Article::body' ],
            [ Id::class, 'Acme\PSR4\ActiveRecord\Article::id' ],
            [ Serial::class, 'Acme\PSR4\ActiveRecord\Article::id' ],
            [ Varchar::class, 'Acme\PSR4\ActiveRecord\Article::slug' ],
            [ Varchar::class, 'Acme\PSR4\ActiveRecord\Article::title' ],
        ], $this->collectProperties($actual));
    }

    public function testForClass(): void
    {
        $forClass = Attributes::forClass(ArticleController::class);

        $this->assertEquals([
            Resource::class,
        ], $forClass->classAttributes);

        $this->assertEquals([
            'list' => [ Route::class ],
            'show' => [ Route::class ],
            'aMethod' => [ Route::class ],
        ], $forClass->methodsAttributes);
    }

    /**
     * @template T of object
     *
     * @param array<TargetClass<T>> $targets
     *
     * @return array<array{class-string<T>, class-string}>
     */
    private function collectClasses(array $targets): array
    {
        $methods = [];

        foreach ($targets as $target) {
            $methods[] = [ $target->attribute, $target->name ];
        }

        usort($methods, fn($a, $b) => $a[1] <=> $b[1]);

        return $methods;
    }

    /**
     * @template T of object
     *
     * @param array<TargetMethod<T>> $targets
     *
     * @return array<array{class-string<T>, string}>
     */
    private function collectMethods(array $targets): array
    {
        $methods = [];

        foreach ($targets as $target) {
            $methods[] = [ $target->attribute, "$target->class::$target->name" ];
        }

        usort($methods, fn($a, $b) => $a[1] <=> $b[1]);

        return $methods;
    }

    /**
     * @template T of object
     *
     * @param TargetParameter<T>[] $targets
     *
     * @return array<array{class-string<T>, string}>
     */
    private function collectParameters(array $targets): array
    {
        $parameters = [];

        foreach ($targets as $target) {
            $parameters[] = [ $target->attribute, "$target->class::$target->method($target->name)" ];
        }

        usort($parameters, fn($a, $b) => $a[1] <=> $b[1]);

        return $parameters;
    }

    /**
     * @template T of object
     *
     * @param TargetProperty<T>[] $targets
     *
     * @return array<array{class-string<T>, string}>
     */
    private function collectProperties(array $targets): array
    {
        $properties = [];

        foreach ($targets as $target) {
            $properties[] = [ $target->attribute, "$target->class::$target->name" ];
        }

        usort($properties, fn($a, $b) => $a[1] <=> $b[1]);

        return $properties;
    }
}
