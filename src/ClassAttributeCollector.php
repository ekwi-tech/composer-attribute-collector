<?php

namespace Ekwi\ComposerAttributeCollector;

use Attribute;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;

/**
 * @internal
 */
class ClassAttributeCollector
{
    /**
     * Cache for collectable attributes status.
     *
     * @var array<class-string, bool>
     */
    private array $collectableAttributes = [];

    public function __construct(
        private readonly Logger $log,
    ) {
    }

    /**
     * @param class-string $class
     *
     * @return array{
     *     array<TransientTargetClass>,
     *     array<TransientTargetMethod>,
     *     array<TransientTargetProperty>,
     *     array<TransientTargetParameter>,
     * }
     *
     * @throws ReflectionException
     */
    public function collectAttributes(string $class): array
    {
        $classReflection = new ReflectionClass($class);

        if (self::isAttribute($classReflection)) {
            return [ [], [], [], [] ];
        }

        $classAttributes = [];
        $attributes = $classReflection->getAttributes();

        foreach ($attributes as $attribute) {
            if (!$this->isCollectable($attribute)) {
                continue;
            }

            $this->log->debug("Found attribute {$attribute->getName()} on $class");

            $classAttributes[] = new TransientTargetClass(
                $attribute->getName(),
            );
        }

        /** @var array<TransientTargetMethod> $methodAttributes */
        $methodAttributes = [];
        /** @var array<TransientTargetParameter> $parameterAttributes */
        $parameterAttributes = [];

        foreach ($classReflection->getMethods() as $methodReflection) {
            $this->collectMethodAndParameterAttributes(
                $class,
                $methodReflection,
                $methodAttributes,
                $parameterAttributes,
            );
        }

        $propertyAttributes = [];

        foreach ($classReflection->getProperties() as $propertyReflection) {
            foreach ($propertyReflection->getAttributes() as $attribute) {
                if (!$this->isCollectable($attribute)) {
                    continue;
                }

                $property = $propertyReflection->name;
                assert($property !== '');

                $this->log->debug("Found attribute {$attribute->getName()} on $class::$property");

                $propertyAttributes[] = new TransientTargetProperty(
                    $attribute->getName(),
                    $property,
                );
            }
        }

        return [ $classAttributes, $methodAttributes, $propertyAttributes, $parameterAttributes ];
    }

    /**
     * Determines if a class is an attribute.
     *
     * @param ReflectionClass<object> $classReflection
     */
    private static function isAttribute(ReflectionClass $classReflection): bool
    {
        foreach ($classReflection->getAttributes() as $attribute) {
            if ($attribute->getName() === Attribute::class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if an attribute is collectable.
     *
     * An attribute is collectable if it is marked with the {@link CollectableAttribute} attribute.
     *
     * @param ReflectionAttribute<object> $attribute
     */
    private function isCollectable(ReflectionAttribute $attribute): bool
    {
        $name = $attribute->getName();

        if (isset($this->collectableAttributes[$name])) {
            return $this->collectableAttributes[$name];
        }

        try {
            $reflection = new ReflectionClass($name);
            $isCollectable = false;

            foreach ($reflection->getAttributes() as $attr) {
                if ($attr->getName() === CollectableAttribute::class) {
                    $isCollectable = true;
                    break;
                }
            }

            return $this->collectableAttributes[$name] = $isCollectable;
        } catch (ReflectionException) {
            return $this->collectableAttributes[$name] = false;
        }
    }

    /**
     * @param array<TransientTargetMethod> $methodAttributes
     * @param array<TransientTargetParameter> $parameterAttributes
     */
    private function collectMethodAndParameterAttributes(
        string $class,
        \ReflectionMethod $methodReflection,
        array &$methodAttributes,
        array &$parameterAttributes,
    ): void {
        foreach ($methodReflection->getAttributes() as $attribute) {
            if (!$this->isCollectable($attribute)) {
                continue;
            }

            $method = $methodReflection->name;

            $this->log->debug("Found attribute {$attribute->getName()} on $class::$method");

            $methodAttributes[] = new TransientTargetMethod(
                $attribute->getName(),
                $method,
            );
        }

        $parameterAttributes = array_merge(
            $parameterAttributes,
            $this->collectParameterAttributes($methodReflection),
        );
    }

    /**
     * @return array<TransientTargetParameter>
     */
    private function collectParameterAttributes(\ReflectionMethod $reflectionFunctionAbstract): array
    {
        $targets = [];
        $class = $reflectionFunctionAbstract->class;
        $method = $reflectionFunctionAbstract->name;

        foreach ($reflectionFunctionAbstract->getParameters() as $parameter) {
            /** @var non-empty-string $name */
            $name = $parameter->name;

            $paramLabel = $class . '::' . $method . '(' . $name . ')';

            foreach ($parameter->getAttributes() as $attribute) {
                if (!$this->isCollectable($attribute)) {
                    continue;
                }

                $this->log->debug("Found attribute {$attribute->getName()} on $paramLabel");

                $targets[] = new TransientTargetParameter(
                    $attribute->getName(),
                    $method,
                    $name
                );
            }
        }

        return $targets;
    }
}
