<?php

namespace tests\Ekwi\ComposerAttributeCollector;

use Acme\PSR4\ActiveRecord\Article;
use Acme\PSR4\CreateMenu;
use Acme\PSR4\CreateMenuHandler;
use Acme\PSR4\Presentation\ArticleController;
use Acme\PSR4\SubscriberA;
use Attribute;
use Ekwi\ComposerAttributeCollector\ClassAttributeCollector;
use Ekwi\ComposerAttributeCollector\TransientTargetClass;
use Ekwi\ComposerAttributeCollector\TransientTargetMethod;
use Ekwi\ComposerAttributeCollector\TransientTargetParameter;
use Ekwi\ComposerAttributeCollector\TransientTargetProperty;
use PHPUnit\Framework\TestCase;
use ReflectionException;

final class ClassAttributeCollectorTest extends TestCase
{
    private ClassAttributeCollector $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new ClassAttributeCollector(new FakeLogger());
    }

    /**
     * @dataProvider provideCollectAttributes
     *
     * @param class-string $class
     * @param array<int|string, mixed> $expected
     *
     * @throws ReflectionException
     */
    public function testCollectAttributes(string $class, array $expected): void
    {
        $actual = $this->sut->collectAttributes($class);

        $this->assertEquals($expected, $actual);
    }

    /** @phpstan-ignore-next-line */
    public static function provideCollectAttributes(): array
    {
        return [

            [
                Attribute::class,
                [
                    [],
                    [],
                    [],
                    [],
                ]
            ],

            [
                CreateMenu::class,
                [
                    [
                        new TransientTargetClass('Acme\Attribute\Permission'),
                        new TransientTargetClass('Acme\Attribute\Permission'),
                    ],
                    [],
                    [],
                    [],
                ]
            ],

            [
                CreateMenuHandler::class,
                [
                    [
                        new TransientTargetClass('Acme\Attribute\Handler'),
                    ],
                    [],
                    [],
                    [],
                ]
            ],

            [
                ArticleController::class,
                [
                    [
                        new TransientTargetClass('Acme\Attribute\Resource'),
                    ],
                    [
                        new TransientTargetMethod('Acme\Attribute\Route', 'list'),
                        new TransientTargetMethod('Acme\Attribute\Route', 'show'),
                        new TransientTargetMethod('Acme\Attribute\Route', 'aMethod'),
                    ],
                    [],
                    [
                        new TransientTargetParameter('Acme81\Attribute\ParameterA', 'aMethod', 'myParameter'),
                        new TransientTargetParameter('Acme81\Attribute\ParameterB', 'aMethod', 'anotherParameter'),
                        new TransientTargetParameter(
                            'Acme81\Attribute\ParameterA',
                            'aMethod',
                            'yetAnotherParameter',
                        ),
                    ],
                ]
            ],

            [
                SubscriberA::class,
                [
                    [],
                    [
                        new TransientTargetMethod('Acme\Attribute\Subscribe', 'onEventA'),
                    ],
                    [],
                    [],
                ]
            ],

            [
                Article::class,
                [
                    [
                        new TransientTargetClass('Acme\Attribute\ActiveRecord\Index'),
                    ],
                    [
                    ],
                    [
                        new TransientTargetProperty('Acme\Attribute\ActiveRecord\Id', 'id'),
                        new TransientTargetProperty('Acme\Attribute\ActiveRecord\Serial', 'id'),
                        new TransientTargetProperty('Acme\Attribute\ActiveRecord\Varchar', 'title'),
                        new TransientTargetProperty('Acme\Attribute\ActiveRecord\Varchar', 'slug'),
                        new TransientTargetProperty('Acme\Attribute\ActiveRecord\Text', 'body'),
                        new TransientTargetProperty('Acme\Attribute\ActiveRecord\Boolean', 'active'),
                    ],
                    [],
                ]
            ],

        ];
    }
}
