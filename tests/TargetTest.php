<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Acme\Attribute\ActiveRecord\Varchar;
use Acme\Attribute\Permission;
use Acme\Attribute\Resource;
use Acme\Attribute\Route;
use Acme\PSR4\ActiveRecord\Article;
use Acme\PSR4\CreateMenu;
use Acme\PSR4\Presentation\ArticleController;
use Acme81\Attribute\ParameterA;
use LogicException;
use Ekwi\ComposerAttributeCollector\TargetClass;
use Ekwi\ComposerAttributeCollector\TargetMethod;
use Ekwi\ComposerAttributeCollector\TargetParameter;
use Ekwi\ComposerAttributeCollector\TargetProperty;
use PHPUnit\Framework\TestCase;

final class TargetTest extends TestCase
{
    public function testTargetClass(): void
    {
        $target = new TargetClass(Resource::class, ArticleController::class);

        $this->assertSame(Resource::class, $target->getAttributeClass());
        $this->assertSame(ArticleController::class, $target->getName());

        $attribute = $target->getAttribute();

        $this->assertInstanceOf(Resource::class, $attribute);
        $this->assertSame('articles', $attribute->name);
        $this->assertSame($attribute, $target->getAttribute(), "the instance should be reused");
    }

    public function testTargetClassWithRepeatableAttributeReturnsTheFirstOne(): void
    {
        $target = new TargetClass(Permission::class, CreateMenu::class);

        $this->assertSame('is_admin', $target->getAttribute()->permission);
    }

    public function testTargetClassFailsWhenTheAttributeIsMissing(): void
    {
        $target = new TargetClass(Resource::class, Article::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            "Unable to find the attribute " . Resource::class . " on " . Article::class
        );

        $target->getAttribute();
    }

    public function testTargetMethod(): void
    {
        $target = new TargetMethod(Route::class, ArticleController::class, 'list');

        $this->assertSame(Route::class, $target->getAttributeClass());
        $this->assertSame(ArticleController::class, $target->getClass());
        $this->assertSame('list', $target->getName());

        $attribute = $target->getAttribute();

        $this->assertInstanceOf(Route::class, $attribute);
        $this->assertSame('/articles', $attribute->pattern);
        $this->assertSame('articles:list', $attribute->id);
        $this->assertSame($attribute, $target->getAttribute(), "the instance should be reused");
    }

    public function testTargetMethodFailsWhenTheAttributeIsMissing(): void
    {
        $target = new TargetMethod(Resource::class, ArticleController::class, 'list');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            "Unable to find the attribute " . Resource::class . " on " . ArticleController::class
            . "::list"
        );

        $target->getAttribute();
    }

    public function testTargetProperty(): void
    {
        $target = new TargetProperty(Varchar::class, Article::class, 'slug');

        $this->assertSame(Varchar::class, $target->getAttributeClass());
        $this->assertSame(Article::class, $target->getClass());
        $this->assertSame('slug', $target->getName());

        $attribute = $target->getAttribute();

        $this->assertInstanceOf(Varchar::class, $attribute);
        $this->assertSame(80, $attribute->size);
        $this->assertTrue($attribute->unique);
        $this->assertSame($attribute, $target->getAttribute(), "the instance should be reused");
    }

    public function testTargetPropertyFailsWhenTheAttributeIsMissing(): void
    {
        $target = new TargetProperty(Varchar::class, Article::class, 'body');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            "Unable to find the attribute " . Varchar::class . " on " . Article::class . "::body"
        );

        $target->getAttribute();
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testTargetParameter(): void
    {
        $target = new TargetParameter(
            ParameterA::class,
            ArticleController::class,
            'aMethod',
            'myParameter',
        );

        $this->assertSame(ParameterA::class, $target->getAttributeClass());
        $this->assertSame(ArticleController::class, $target->getClass());
        $this->assertSame('aMethod', $target->getMethod());
        $this->assertSame('myParameter', $target->getName());

        $attribute = $target->getAttribute();

        $this->assertInstanceOf(ParameterA::class, $attribute);
        $this->assertSame('my parameter label', $attribute->label);
        $this->assertSame($attribute, $target->getAttribute(), "the instance should be reused");
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testTargetParameterFailsWhenTheParameterIsMissing(): void
    {
        $target = new TargetParameter(
            ParameterA::class,
            ArticleController::class,
            'aMethod',
            'undefinedParameter',
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            "Unable to find the attribute " . ParameterA::class . " on " . ArticleController::class
            . "::aMethod(undefinedParameter)"
        );

        $target->getAttribute();
    }
}
