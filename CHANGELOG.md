# CHANGELOG

This project is a fork of [olvlvl/composer-attribute-collector][upstream]. Entries up to and
including `v2.1.2` are upstream's; the fork's own releases start at `v2.2.0`, and `3.0.0` is where
its API parted ways with upstream. Version numbers are the fork's own—upstream publishes a `v3.0.x`
of its own, unrelated to this one.

## Unreleased

### New Requirements

None

### New features

None

### Deprecated Features

None

### Backward Incompatible Changes

None

### Other Changes

None


## 3.0.0

### New Requirements

None

### New features

`TargetClass`, `TargetMethod`, `TargetProperty`, and `TargetParameter` have a `getAttribute()`
method that instantiates the attribute on demand, using reflection on the target. The instance is
created on first use, then reused. For a repeatable attribute, the first one found on the target is
returned.

### Deprecated Features

None

### Backward Incompatible Changes

The classes moved from the `olvlvl\ComposerAttributeCollector` namespace to
`Ekwi\ComposerAttributeCollector`. Rewrite your imports; the generated "attributes" file is
refreshed on the next `composer dump-autoload` and needs no manual migration.

The package no longer declares `replace: { "olvlvl/composer-attribute-collector": "self.version" }`.
It is not a drop-in replacement for upstream and no longer pretends to be one. Composer can now
install both packages at once—don't: both plugins write `vendor/attributes.php`.

Attribute arguments are no longer collected. The generated "attributes" file only records the names
of the attributes and of their targets, because arguments can hold arbitrary values—objects, in
particular—that cannot be rendered as PHP code and break the generated file unless they implement
`__set_state()`.

The properties of `TargetClass`, `TargetMethod`, `TargetProperty`, and `TargetParameter` are private
and replaced by accessors: `getAttributeClass()` (the name of the attribute class), `getName()`,
`getClass()`, and `getMethod()`. The constructors are unchanged: they still take the name of the
attribute class first. `getAttributeClass()` returns the **name** of the attribute class where
upstream's `attribute` property holds an instance, and `ForClass` exposes attribute names instead of
instances.

### Other Changes

The fork stopped synchronizing with upstream; the monthly `Sync upstream` workflow is gone.


## v2.1.2

### New Requirements

None

### New features

None

### Deprecated Features

None

### Backward Incompatible Changes

None

### Other Changes

Execute the collector with `/usr/bin/env php` instead of executing the script directly, because the
permission might be dropped by indelicate unarchivers. (fix #49)



## v2.1.1

### New Requirements

- composer-plugin-api v2.3

### New features

None

### Deprecated Features

None

### Backward Incompatible Changes

None

### Other Changes

None



## v2.1.0

### New Requirements

None

### New features

- [#38](https://github.com/olvlvl/composer-attribute-collector/pull/38) Attributes are now collected from interfaces and traits as well as classes. (@olvlvl)

- [#37](https://github.com/olvlvl/composer-attribute-collector/pull/37) Parameter attributes are now collected. Use the method `findTargetParameters()` to find target parameters, and the method `filterTargetParameters()` to filter target parameters according to a predicate. (@staabm @olvlvl)

- [#39](https://github.com/olvlvl/composer-attribute-collector/pull/39) The `InheritsAttributes` attribute can be used on classes that inherit their attributes from traits, properties, or methods, but don't use attributes themselves, and were previously ignored by the collection process. (@olvlvl)

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

- [#44](https://github.com/olvlvl/composer-attribute-collector/pull/44) The collector automatically scans `autoload` paths of the root `composer.json` for a zero-configuration experience. (@olvlvl)

### Deprecated Features

None

### Backward Incompatible Changes

None

### Other Changes

[#35](https://github.com/olvlvl/composer-attribute-collector/pull/35) The collector runs as a command to avoid clashes between packages used by Composer and those used by the application, such as incompatible signatures between different versions of the PSR Logger. (@olvlvl)

Added use cases for [incompatible signature](https://github.com/olvlvl/composer-attribute-collector/pull/40), [Symfony](https://github.com/olvlvl/composer-attribute-collector/pull/43), and [Laravel](https://github.com/olvlvl/composer-attribute-collector/pull/42).



## v2.0.2

### New Requirements

None

### New features

None

### Deprecated Features

None

### Backward Incompatible Changes

None

### Other Changes

- Fix PHP 8.4 deprecation notice "Implicitly marking parameter * as nullable is deprecated."
- Simplify attribute creation functions.



## v2.0.1

### New Requirements

None

### New features

None

### Deprecated Features

None

### Backward Incompatible Changes

None

### Other Changes

- #26 Fix enum support on PHP < 8.2.0 (@mnavarrocarter)



## v2.0.0

### New Requirements

None

### New features

- The plugin now collects attributes on properties. `Attributes::findTargetProperties()` returns target properties, and `filterTargetProperties()` filters properties with a predicate.

### Deprecated Features

- The `ignore-paths` directive has been replaced by `exclude`.

### Backward Incompatible Changes

- The paths defined by the `include` and `exclude` directives are relative to the `composer.json` file. The `{vendor}` placeholder is replaced by the absolute path to the vendor directory.

### Other Changes

- The plugin no longer uses a file cache by default. To persist a cache between runs, set the environment variable `COMPOSER_ATTRIBUTE_COLLECTOR_USE_CACHE` to `1`, `yes`, or `true`.



## v1.2.0

### New Requirements

None

### New features

- [#11](https://github.com/olvlvl/composer-attribute-collector/pull/11) Attribute instantiation errors are decorated to help find origin (@withinboredom @olvlvl)
- [#12](https://github.com/olvlvl/composer-attribute-collector/pull/12) `Attributes::filterTargetClasses()` can filter target classes using a predicate (@olvlvl)
- [#12](https://github.com/olvlvl/composer-attribute-collector/pull/12) `Attributes::filterTargetMethods()` can filter target methods using a predicate. `Attributes::predicateForAttributeInstanceOf()` can be used to create a predicate to filter classes or methods targeted by an attribute class or subclass (@olvlvl)
- [#10](https://github.com/olvlvl/composer-attribute-collector/pull/10) 3 types of cache speed up generation by limiting updates to changed files (@xepozz @olvlvl)

### Deprecated Features

None

### Backward Incompatible Changes

None

### Other Changes

None



## v1.1.0

### New Requirements

None

### New features

- File paths matching `symfony/cache/Traits` are ignored.
- The option `extra.composer-attribute-collection.ignore-paths` can be used to ignore paths.

### Deprecated Features

None

### Backward Incompatible Changes

None

### Other Changes

None

<!--

## vX.x to vX.x

### New Requirements

None

### New features

None

### Deprecated Features

None

### Backward Incompatible Changes

None

### Other Changes

None

-->

[upstream]: https://github.com/olvlvl/composer-attribute-collector
