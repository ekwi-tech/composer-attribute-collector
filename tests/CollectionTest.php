<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Acme\Attribute\ActiveRecord\Id;
use Acme\Attribute\ActiveRecord\Index;
use Acme\Attribute\ActiveRecord\SchemaAttribute;
use Acme\Attribute\ActiveRecord\Serial;
use Acme\Attribute\ActiveRecord\Text;
use Acme\Attribute\ActiveRecord\Varchar;
use Acme\Attribute\Get;
use Acme\Attribute\Post;
use Acme\Attribute\Route;
use Acme\Presentation\FileController;
use Acme\Presentation\ImageController;
use Acme\PSR4\ActiveRecord\Article;
use Acme\PSR4\Presentation\ArticleController;
use Acme81\Attribute\ParameterA;
use Acme81\Attribute\ParameterB;
use Ekwi\ComposerAttributeCollector\Attributes;
use Ekwi\ComposerAttributeCollector\Collection;
use Ekwi\ComposerAttributeCollector\TargetClass;
use Ekwi\ComposerAttributeCollector\TargetMethod;
use Ekwi\ComposerAttributeCollector\TargetParameter;
use Ekwi\ComposerAttributeCollector\TargetProperty;
use PHPUnit\Framework\TestCase;

use function in_array;

final class CollectionTest extends TestCase
{
    public function testFindTargetClasses(): void
    {
        $collection = new Collection(
            targetClasses: [
                Route::class => [
                    ArticleController::class,
                    ImageController::class,
                ],
                Index::class => [
                    Article::class,
                ],
            ],
            targetMethods: [
            ],
            targetProperties: [
            ],
            targetParameters: [
            ]
        );

        $this->assertEquals([
            new TargetClass(Route::class, ArticleController::class),
            new TargetClass(Route::class, ImageController::class),
        ], $collection->findTargetClasses(Route::class));

        $this->assertSame([], $collection->findTargetClasses(Get::class));
    }

    public function testFindTargetMethods(): void
    {
        $collection = new Collection(
            targetClasses: [
            ],
            targetMethods: [
                Route::class => [
                    [ ArticleController::class, 'recent' ],
                    [ ArticleController::class, 'show' ],
                ],
            ],
            targetProperties: [
            ],
            targetParameters: [
            ]
        );

        $this->assertEquals([
            new TargetMethod(Route::class, ArticleController::class, 'recent'),
            new TargetMethod(Route::class, ArticleController::class, 'show'),
        ], $collection->findTargetMethods(Route::class));
    }

    public function testFindTargetProperties(): void
    {
        $collection = new Collection(
            targetClasses: [
            ],
            targetMethods: [
            ],
            targetProperties: [
                Varchar::class => [
                    [ Article::class, 'title' ],
                    [ Article::class, 'slug' ],
                ],
            ],
            targetParameters: [
            ]
        );

        $this->assertEquals([
            new TargetProperty(Varchar::class, Article::class, 'title'),
            new TargetProperty(Varchar::class, Article::class, 'slug'),
        ], $collection->findTargetProperties(Varchar::class));
    }

    public function testFindTargetParameters(): void
    {
        $collection = new Collection(
            targetClasses: [
            ],
            targetMethods: [
            ],
            targetProperties: [
            ],
            targetParameters: [
                ParameterA::class => [
                    [ ArticleController::class, 'myMethod', 'myParamA' ],
                ],
            ]
        );

        $this->assertEquals([
            new TargetParameter(ParameterA::class, ArticleController::class, 'myMethod', 'myParamA'),
        ], $collection->findTargetParameters(ParameterA::class));
    }

    public function testFilterTargetClasses(): void
    {
        $collection = new Collection(
            targetClasses: [
                Route::class => [
                    ArticleController::class,
                    ImageController::class,
                    FileController::class,
                ],
            ],
            targetMethods: [
            ],
            targetProperties: [
            ],
            targetParameters: [
            ]
        );

        $actual = $collection->filterTargetClasses(
            fn($a, $c) => in_array($c, [ ArticleController::class, ImageController::class ])
        );

        $this->assertEquals([
            new TargetClass(Route::class, ArticleController::class),
            new TargetClass(Route::class, ImageController::class),
        ], $actual);
    }

    public function testFilterTargetMethods(): void
    {
        $collection = new Collection(
            targetClasses: [
            ],
            targetMethods: [
                Route::class => [
                    [ ArticleController::class, 'recent' ],
                ],
                Get::class => [
                    [ ArticleController::class, 'show' ],
                ],
                Post::class => [
                    [ ArticleController::class, 'create' ],
                ],
            ],
            targetProperties: [
            ],
            targetParameters: [
            ]
        );

        $actual = $collection->filterTargetMethods(fn($a) => is_a($a, Route::class, true));

        $this->assertEquals([
            new TargetMethod(Route::class, ArticleController::class, 'recent'),
            new TargetMethod(Get::class, ArticleController::class, 'show'),
            new TargetMethod(Post::class, ArticleController::class, 'create'),
        ], $actual);
    }

    public function testFilterTargetParameters(): void
    {
        $collection = new Collection(
            targetClasses: [
            ],
            targetMethods: [
            ],
            targetProperties: [
            ],
            targetParameters: [
                ParameterA::class => [
                    [ ArticleController::class, 'myMethod', 'myParamA' ],
                    [ ArticleController::class, 'myMethod', 'myParamA2' ],
                    [ ArticleController::class, 'myFoo', 'fooParam' ],
                ],
                ParameterB::class => [
                    [ ArticleController::class, 'myMethod', 'myParamB' ],
                ],
            ]
        );

        $actual = $collection->filterTargetParameters(fn($a) => is_a($a, ParameterA::class, true));

        $this->assertEquals([
            new TargetParameter(ParameterA::class, ArticleController::class, 'myMethod', 'myParamA'),
            new TargetParameter(ParameterA::class, ArticleController::class, 'myMethod', 'myParamA2'),
            new TargetParameter(ParameterA::class, ArticleController::class, 'myFoo', 'fooParam'),
        ], $actual);
    }

    public function testFilterTargetProperties(): void
    {
        $collection = new Collection(
            targetClasses: [
            ],
            targetMethods: [
                Route::class => [ // trap
                    [ ArticleController::class, 'recent' ],
                ],
            ],
            targetProperties: [
                Id::class => [
                    [ Article::class, 'id' ],
                ],
                Serial::class => [
                    [ Article::class, 'id' ],
                ],
                Varchar::class => [
                    [ Article::class, 'title' ],
                ],
                Text::class => [
                    [ Article::class, 'body' ],
                ]
            ],
            targetParameters: [
            ]
        );

        $actual = $collection->filterTargetProperties(
            Attributes::predicateForAttributeInstanceOf(SchemaAttribute::class)
        );

        $this->assertEquals([
            new TargetProperty(Id::class, Article::class, 'id'),
            new TargetProperty(Serial::class, Article::class, 'id'),
            new TargetProperty(Varchar::class, Article::class, 'title'),
            new TargetProperty(Text::class, Article::class, 'body'),
        ], $actual);
    }

    public function testForClass(): void
    {
        $collection = new Collection(
            targetClasses: [
                Index::class => [
                    Article::class,
                ],
                Route::class => [ // trap
                    ArticleController::class,
                ],
            ],
            targetMethods: [
                Route::class => [ // trap
                    [ ArticleController::class, 'recent' ],
                ],
            ],
            targetProperties: [
                Id::class => [
                    [ Article::class, 'id' ],
                ],
                Serial::class => [
                    [ Article::class, 'id' ],
                ],
                Varchar::class => [
                    [ Article::class, 'title' ],
                    [ Article::class, 'slug' ],
                ],
                Text::class => [
                    [ Article::class, 'body' ],
                ]
            ],
            targetParameters: [
            ]
        );

        $actual = $collection->forClass(Article::class);

        $this->assertEquals([
            Index::class,
        ], $actual->classAttributes);

        $this->assertEmpty($actual->methodsAttributes);

        $this->assertEquals([
            'id' => [
                Id::class,
                Serial::class,
            ],
            'title' => [
                Varchar::class,
            ],
            'slug' => [
                Varchar::class,
            ],
            'body' => [
                Text::class,
            ]
        ], $actual->propertyAttributes);
    }
}
