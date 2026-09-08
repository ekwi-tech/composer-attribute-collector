# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Composer plugin (`ekwi-tech/composer-attribute-collector`, a detached fork of
`olvlvl/composer-attribute-collector` requiring PHP >= 8.4) that hooks
`post-autoload-dump` and generates `vendor/attributes.php`: a static, reflection-free map of PHP 8
attribute targets (classes, methods, properties, parameters). Consumers read it through the
`Attributes` facade.

The fork no longer synchronizes with upstream and lives in its own
`Ekwi\ComposerAttributeCollector` namespace. It differs from upstream on two central points:

- **Collection is opt-in.** An attribute is only collected if the attribute class itself is marked
  `#[CollectableAttribute]` (see `ClassAttributeCollector::isCollectable()`).
- **Only names are collected, never attribute arguments.** Upstream `var_export`s the arguments so
  that `$target->attribute` can be a hydrated instance; that breaks on any argument PHP cannot
  export as code (an object without `__set_state()`, typically). Here the generated file holds only
  class-strings: `Target*::getAttributeClass()` returns the attribute **class-string**, and
  `Target*::getAttribute()` instantiates the attribute lazily, by reflecting on the target it
  describes (`AttributeInstantiator`). Target properties are private; read them through
  `getName()` / `getClass()` / `getMethod()`.

## Commands

**There is no PHP on the host.** Every target runs inside the image built from `Dockerfile`
(`php:8.4-cli-alpine` + xdebug + composer + a phpcs baked into `/opt/phpcs`), so drive the toolchain
through `make`, never through a bare `php` or `vendor/bin/…` call.

```shell
make                   # list every target
make test              # composer install + clean sandboxes + phpunit
make test-filter FILTER=testTargetMethods
make test-coverage     # HTML coverage in build/coverage
make lint              # phpcs -s (PSR-12) + phpstan level max on src/
make shell             # interactive shell in the image
make test PHP_VERSION=8.5
```

The container runs as the invoking user (`--user $(id -u):$(id -g)`) so nothing it writes ends up
root-owned; `HOME=/tmp` and a composer cache in `var/composer` (gitignored) keep it self-contained.
`PHP_RUN="sh -c"` bypasses docker and runs on a local toolchain — that is what
`.github/workflows/test.yml` passes, since the CI brings its own PHP.

`make test-cleanup` wipes `.composer-attribute-collector/` (cache) and `tests/sandbox/`. Run it, or
the full `make test`, if a stale generated `tests/sandbox/attributes.php` makes tests behave oddly —
`CollectorTestAbstract` generates that file once per test class and `require`s it.

## Architecture

Two runtimes, deliberately separated:

1. **Build time** — `Plugin` (Composer plugin, `extra.class`) subscribes to `post-autoload-dump`,
   builds a `Config`, serializes it to a temp file, and spawns a **separate PHP process**
   (`collector.php`, via `symfony/process`, which is not declared in `composer.json` and comes from
   Composer's own dependencies) to do the work. The subprocess isolation exists so that
   loading application classes for reflection cannot collide with Composer's own loaded
   dependencies (see `cases/incompatible-signature`). `collector.php` unserializes the `Config`
   (with `allowed_classes` restricted to `Config`), builds its own `Logger`, and runs `Collector`.

2. **Run time** — the generated `attributes.php` calls `Attributes::with(fn () => new Collection(…))`
   with plain nested arrays of class/method/property/parameter **names**. `Attributes` is a static
   facade; `Collection` just wraps those names in `Target*` objects on lookup. No reflection, and
   no attribute instantiation, at run time.

### The `Collector::dump()` pipeline

`MemoizeClassMapGenerator` (scan include paths → class map) → `MemoizeClassMapFilter` +
`Filter\Chain[ContentFilter, ClassFilter]` (cheap rejection) → `MemoizeAttributeCollector` wrapping
`ClassAttributeCollector` (reflection, the only expensive step) → `TransientCollectionRenderer`
(hand-rolled export into the PHP file: short arrays, no numeric keys on lists, `var_export` used
only to quote individual strings).

- `ContentFilter` reads the file as **plain text**: no `#[` means skip, and a file that looks like
  it declares an attribute class is skipped. This is why a class inheriting attributes from a trait
  needs `#[InheritsAttributes]` to be seen at all.
- The three `Memoize*` classes each own one key in a `Datastore` (`classmap`, `filtered`,
  `attributes`) and invalidate per-file by `filemtime`. They also prune entries for paths/classes
  that disappeared. `Datastore` is `RuntimeDatastore` (throwaway) unless
  `COMPOSER_ATTRIBUTE_COLLECTOR_USE_CACHE` is truthy, then `FileDatastore` writes serialized state
  to `.composer-attribute-collector/v{MAJOR}-{MINOR}-{key}`.
- **Bump `Plugin::VERSION_MINOR` when you change the shape of anything stored in the datastore**
  (the `Transient*` DTOs, the memoize state tuples). The version is the cache-busting mechanism;
  stale data of the wrong shape is otherwise unserialized and used.

### Transient vs. final targets

`Transient*` classes (`TransientTargetClass/Method/Property/Parameter`) are build-time DTOs holding
an attribute class name and the name of its target. `TargetClass`, `TargetMethod`, `TargetProperty`,
`TargetParameter` are the run-time equivalents handed to users; they hold the same names, privately,
and instantiate the attribute on demand in `getAttribute()`. Both sides carry names only —
if you ever reintroduce argument capture, it has to survive being rendered as PHP code, which is
exactly what this fork removed. Adding a new target kind means touching both sides plus
`TransientCollection`, `TransientCollectionRenderer`, `Collection`, `Attributes`, and `ForClass`.

### Config

`Config::from()` resolves `vendor-dir`, defaults `include` to the root package's `autoload` paths
(minus `attributes.php` itself), and applies `extra.composer-attribute-collector.include/exclude`
with the `{vendor}` placeholder. Paths are absolute; `exclude` is compiled into a single regexp.

## Tests

- `CollectorTestAbstract` holds every behavioural assertion; `CollectorTest` runs the pipeline
  in-process and `PluginTest` runs it through `Plugin::dump()` (the subprocess path). New
  end-to-end expectations go in the abstract class so both paths are covered.
- Fixtures live in `tests/Acme` (PSR-4, plus a `ClassMap` autoloaded via classmap) and
  `tests/Acme81`. Attribute fixtures under `tests/Acme*/Attribute` must carry
  `#[CollectableAttribute]` or nothing will be collected.
- `tests/Acme/PSR4/IncompatibleSignature.php` is deliberately unloadable and is excluded in both
  `composer.json` and the test config.

## Use cases (`cases/`)

Each case is a standalone app pinning the plugin via a `path` repository. `make plugin-copy` copies
`src/`, `composer.json`, and `collector.php` into `cases/<name>/composer-attribute-collector/` —
required before `composer install` there. CI runs all three (incompatible-signature, symfony,
laravel) against PHP 8.0–8.5, so avoid syntax in the *generated* output that older PHP can't parse.
