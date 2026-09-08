# composer-attribute-collector

> [!NOTE]
> **This is a fork of [olvlvl/composer-attribute-collector][upstream].**
> All the credit for the original design and implementation goes to [Olivier Laviale][olvlvl] and
> the contributors of the upstream project—this fork only carries a handful of opinionated changes
> on top of their work, and keeps tracking upstream. It is distributed as
> `ekwi-tech/composer-attribute-collector`, under the same [BSD-3-Clause license](LICENSE).
>
> The public API is **not** compatible with upstream: read
> [Differences from upstream](#differences-from-upstream) before switching.

**composer-attribute-collector** is a [Composer][] plugin designed to effectively _discover_ PHP 8
attribute targets, and later retrieve them at near zero cost, without runtime reflection. After the
autoloader dump, it collects attributes and generates a static file for fast access. This provides a
convenient way to _discover_ attribute-backed classes, methods, or properties—ideal for codebase
analysis. (For known targets, traditional reflection remains an option.)



## Differences from upstream

A handful of deliberate changes; everything else—configuration, caching, the generated file, the
`Attributes` facade—behaves like [upstream][].

### 1. Collection is opt-in

Upstream collects every attribute it finds. Here an attribute is collected only if its own class is
marked with `#[CollectableAttribute]`, which keeps the generated file small and focused on the
attributes you actually query. See [Mark collectable attributes](#2-mark-collectable-attributes).

### 2. Attribute arguments are never dumped

Upstream `var_export`s the arguments of an attribute into the generated file so that `$target->attribute`
can be a ready-made instance. That breaks on any argument PHP cannot render as code—an object
without `__set_state()`, typically.

This fork records **names only**. When you need the arguments, `getAttribute()` instantiates the
attribute on demand by reflecting on the target it describes, and reuses the instance afterwards.
Finding targets stays reflection-free.

### 3. Targets are accessor-based

The properties of `TargetClass`, `TargetMethod`, `TargetProperty`, and `TargetParameter` are
private, and read through `getAttributeClass()`, `getName()`, `getClass()`, and `getMethod()`.
Upstream exposes public properties, and its `attribute` property holds an instance where
`getAttributeClass()` holds a class-string.

### 4. PHP >= 8.4

Upstream supports PHP 8.0 and up; this fork requires PHP 8.4.

### 5. New package name, same namespace

The package is `ekwi-tech/composer-attribute-collector`, but the classes keep the original
`olvlvl\ComposerAttributeCollector` namespace, on purpose: the fork merges upstream changes every
month, and renaming the namespace would turn each of those merges into a conflict on every single
file. Your `use` statements are therefore unchanged when you switch.

> [!IMPORTANT]
> The package declares `replace: { "olvlvl/composer-attribute-collector": "self.version" }`, so it
> cannot be installed side by side with upstream, and Composer considers any requirement of the
> upstream package satisfied. That is convenient for an application that switches deliberately, but
> a third-party dependency written against upstream's API will **not** work with this fork.



#### Features

- Almost zero configuration: mark the attributes you want collected, and you're done
- No reflection when finding targets
- Might improve performance
- No dependency (except Composer of course)
- A single interface to get attribute targets: classes, methods, properties, and parameters
- Only names are collected, so no attribute argument can break the generated file
- Attributes are still available, instantiated on demand with `getAttribute()`
- Can cache discoveries to speed up consecutive runs.

> [!NOTE]
> Currently, the plugin supports class, method, property, and parameter targets.
> You're welcome to [contribute](CONTRIBUTING.md) if you're interested in expending its support.

> [!WARNING]
> Attributes used on functions are ignored at this time.



#### Usage

The following example demonstrates how targets and their attributes can be retrieved:

```php
<?php

use olvlvl\ComposerAttributeCollector\Attributes;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\Mapping\Column;

require_once 'vendor/autoload.php';
require_once 'vendor/attributes.php'; // <-- the file created by the plugin

// Find the target classes of the AsMessageHandler attribute.
foreach (Attributes::findTargetClasses(AsMessageHandler::class) as $target) {
    // getAttributeClass() is the name of the attribute class,
    // getName() the name of the target class, and
    // getAttribute() an instance of the attribute, created on demand.
    var_dump($target->getAttributeClass(), $target->getName(), $target->getAttribute());
}

// Find the target methods of the Route attribute.
foreach (Attributes::findTargetMethods(Route::class) as $target) {
    var_dump($target->getAttributeClass(), $target->getClass(), $target->getName());
}

// Find the target properties of the Column attribute.
foreach (Attributes::findTargetProperties(Column::class) as $target) {
    var_dump($target->getAttributeClass(), $target->getClass(), $target->getName());
}

// Find the target method parameters of the UserInput attribute.
foreach (Attributes::findTargetParameters(UserInput::class) as $target) {
    var_dump(
        $target->getAttributeClass(),
        $target->getClass(),
        $target->getMethod(),
        $target->getName(),
    );
}

// Filter target methods using a predicate.
// You can also filter target classes and properties.
$predicate = fn($attribute) => is_a($attribute, Route::class, true);
# or
$predicate = Attributes::predicateForAttributeInstanceOf(Route::class);

foreach (Attributes::filterTargetMethods($predicate) as $target) {
    var_dump($target->getAttributeClass(), $target->getClass(), $target->getName());
}

// Find class, method, and property attribute names for the ArticleController class.
$attributes = Attributes::forClass(ArticleController::class);

var_dump($attributes->classAttributes);
var_dump($attributes->methodsAttributes);
var_dump($attributes->propertyAttributes);
```

> [!IMPORTANT]
> The plugin collects **names**, not attribute instances: the generated file records which classes,
> methods, properties, and parameters an attribute is used on, and nothing else. Attribute
> _arguments_ are not collected, because they can hold arbitrary values—objects in particular—that
> cannot be rendered as PHP code in the generated file.
>
> If you need the arguments of an attribute, `getAttribute()` instantiates it on demand, using
> reflection on the target—the instance is created on first use, then reused:
>
> ```php
> foreach (Attributes::findTargetClasses(AsMessageHandler::class) as $target) {
>     $attribute = $target->getAttribute(); // an AsMessageHandler instance
>
>     var_dump($attribute->fromTransport);
> }
> ```
>
> Reflection only happens when `getAttribute()` is called; finding targets remains reflection-free.
> If an attribute is repeatable, `getAttribute()` returns the first one found on the target.



## Getting started

Here are a few steps to get you started.

### 1\. Install the plugin

The package is not published on [packagist.org][]: it is resolved from the Git tags of this
repository. Declare the repository, then require the package with [Composer][]. You will be asked
if you trust the plugin and wish to activate it, select `y` to proceed.

```shell
composer config repositories.composer-attribute-collector vcs https://github.com/ekwi-tech/composer-attribute-collector
composer require ekwi-tech/composer-attribute-collector
```

You should see log messages similar to this:

```
Generating autoload files
Generating attributes file
Generated attributes file in 9.137 ms
Generated autoload files
```

> [!TIP]
> See the [Frequently Asked Questions](#frequently-asked-questions) section
> to automatically refresh the "attributes" file during development.



### 2\. Mark collectable attributes

For an attribute to be collected, it must be marked with the `#[CollectableAttribute]` attribute.
This opt-in strategy ensures that only the attributes you're interested in are collected,
improving performance and reducing noise.

```php
<?php

namespace App\Attribute;

use Attribute;
use olvlvl\ComposerAttributeCollector\CollectableAttribute;

#[CollectableAttribute]
#[Attribute(Attribute::TARGET_CLASS)]
final class MyAttribute
{
}
```

### 3\. Configure the plugin (optional)

The collector automatically scans `autoload` paths of the root `composer.json` for a
zero-configuration experience. You can override them via
`extra.composer-attribute-collector.include`.

```json
{
  "extra": {
    "composer-attribute-collector": {
      "include": [
        "src"
      ]
    }
  }
}
```

Check the [Configuration options](#configuration) for more details.



### 4\. Autoload the "attributes" file

You can require the "attributes" file using `require_once 'vendor/attributes.php';` but you might
prefer to use Composer's autoloading feature:

```json
{
  "autoload": {
    "files": [
      "vendor/attributes.php"
    ]
  }
}
```



## Configuration

Here are a few ways you can configure the plugin.



### Including paths or files ([root-only][])

The collector automatically scans `autoload` paths of the root `composer.json`, but you can override
them via the `include` property.

The specified paths are relative to the `composer.json` file, and the `{vendor}` placeholder is
replaced with the path to the vendor folder.

```json
{
  "extra": {
    "composer-attribute-collector": {
      "include": [
        "path-or-file/to/include"
      ]
    }
  }
}
```

### Excluding paths or files ([root-only][])

Use the `exclude` property to exclude paths or files from scanning. This is handy when files
cause issues or have side effects.

The specified paths are relative to the `composer.json` file, and the `{vendor}` placeholder is
replaced with the path to the vendor folder.

```json
{
  "extra": {
    "composer-attribute-collector": {
      "exclude": [
        "path-or-file/to/exclude"
      ]
    }
  }
}
```

### Cache discoveries between runs

The plugin is able to maintain a cache to reuse discoveries between runs. To enable the cache,
set the environment variable `COMPOSER_ATTRIBUTE_COLLECTOR_USE_CACHE` to `1`, `yes`, or `true`.
Cache items are persisted in the `.composer-attribute-collector` directory, you might want to add
it to your `.gitignore` file.

```shell
COMPOSER_ATTRIBUTE_COLLECTOR_USE_CACHE=1 composer dump-autoload
```



## Use cases

Use cases are available to test the plugin in real conditions:

- [Incompatible signature](cases/incompatible-signature) The plugin is able to collect attributes,
  although the PSR Logger version used by Composer and the application are incompatible.

- [Symfony](cases/symfony) A Symfony application, created with `symfony new`.

- [Laravel](cases/laravel) A Laravel application, created with `laravel new`.

> [!WARNING]
> The Symfony and Laravel cases still require `olvlvl/composer-attribute-collector` and Composer
> resolves it from packagist.org rather than from the local `path` repository, so those two
> currently exercise **upstream**, not this fork. They need to be rewired: requiring the fork by
> its own name, and using attributes of their own that can be marked `#[CollectableAttribute]`.



## Frequently Asked Questions

**Do I need to generate an optimized autoloader?**

You don't need to generate an optimized autoloader for this to work. The plugin uses code similar
to Composer to find classes. Anything that works with Composer should work with the plugin.

**Can I use the plugin during development?**

Yes, you can use the plugin during development, but keep in mind the "attributes" file is only
generated after the autoloader is dumped. If you modify attributes you will have to run
`composer dump-autoload` to refresh the "attributes" file.

As a workaround you could have watchers on the directories that contain classes with attributes to
run `XDEBUG_MODE=off composer dump-autoload` when you make changes. [PhpStorm offers file
watchers][phpstorm-watchers]. You could also use [spatie/file-system-watcher][], it only requires
PHP. If the plugin is too slow for your liking, try running the command with
`COMPOSER_ATTRIBUTE_COLLECTOR_USE_CACHE=yes`, it will enable caching and speed up consecutive runs.

**How do I include a class that inherits its attributes?**

To speed up the collection process, the plugin first looks at PHP files as plain text for hints of
attribute usage. If a class inherits its attributes from traits, properties, or methods, but doesn't
use attributes itself, it will be ignored. Use the attribute
`#[olvlvl\ComposerAttributeCollector\InheritsAttributes]` to force the collection.
Note that the attributes you want to collect must still be marked with `#[CollectableAttribute]`.

```php
trait UrlTrait
{
    #[UrlGetter]
    public function get_url(): string
    {
        return '/url';
    }
}

#[InheritsAttributes]
class InheritedAttributeSample
{
    use UrlTrait;
}
```

----------



## Continuous Integration

The project is continuously tested by [GitHub actions](https://github.com/ekwi-tech/composer-attribute-collector/actions).

[![Cases](https://github.com/ekwi-tech/composer-attribute-collector/actions/workflows/cases.yml/badge.svg?branch=main)](https://github.com/ekwi-tech/composer-attribute-collector/actions/workflows/cases.yml)
[![Tests](https://github.com/ekwi-tech/composer-attribute-collector/actions/workflows/test.yml/badge.svg?branch=main)](https://github.com/ekwi-tech/composer-attribute-collector/actions/workflows/test.yml)
[![Static Analysis](https://github.com/ekwi-tech/composer-attribute-collector/actions/workflows/quality.yml/badge.svg?job=phpstan)](https://github.com/ekwi-tech/composer-attribute-collector/actions/workflows/quality.yml)
[![Code Style](https://github.com/ekwi-tech/composer-attribute-collector/actions/workflows/quality.yml/badge.svg?job=phpcs)](https://github.com/ekwi-tech/composer-attribute-collector/actions/workflows/quality.yml)



## Code of Conduct

This project adheres to a [Contributor Code of Conduct](CODE_OF_CONDUCT.md). By participating in
this project and its community, you're expected to uphold this code.



## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) for details. Issues and pull requests belong to
[this repository](https://github.com/ekwi-tech/composer-attribute-collector); please don't open
them against [upstream][] for changes that are specific to the fork.



## Acknowledgements

This project is a fork of [olvlvl/composer-attribute-collector][upstream] by
[Olivier Laviale][olvlvl]. The design, the implementation, and most of the code and documentation
you are reading are theirs, and the [BSD-3-Clause license](LICENSE) and copyright of the original
work are unchanged. Thank you for the plugin, and for making it free software.



[Composer]:  https://getcomposer.org/
[upstream]:  https://github.com/olvlvl/composer-attribute-collector
[olvlvl]:    https://github.com/olvlvl
[packagist.org]: https://packagist.org/
[root-only]: https://getcomposer.org/doc/04-schema.md#root-package
[spatie/file-system-watcher]: https://github.com/spatie/file-system-watcher
[phpstorm-watchers]: https://www.jetbrains.com/help/phpstorm/using-file-watchers.html
