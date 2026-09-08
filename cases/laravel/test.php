<?php

use App\Attributes\SampleAttribute;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\TargetClass;

require 'vendor/autoload.php';

$actual = Attributes::findTargetClasses(SampleAttribute::class);
$expected = [
    new TargetClass(SampleAttribute::class, App\Models\User::class),
    new TargetClass(SampleAttribute::class, App\Providers\AppServiceProvider::class),
];

$sortFn = fn($a, $b) => strcmp($a->getName(), $b->getName());

usort($actual, $sortFn);
usort($expected, $sortFn);

echo "Found Target Classes:\n";

var_dump($actual);

$actual == $expected or throw new \RuntimeException("Target classes don't match expected");

echo "✓ Expectation matched, Yay!\n";
