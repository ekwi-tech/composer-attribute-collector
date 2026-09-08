# Contributing

Contributions are **welcome** and will be fully **credited**.

We accept contributions via Pull Requests.

## This project is a fork

This repository is a fork of [olvlvl/composer-attribute-collector][upstream]. Open your issues and
pull requests **here**, against `ekwi-tech/composer-attribute-collector`, never against the upstream
repository—the fork carries changes upstream did not ask for, and its `main` has diverged.

The fork no longer merges upstream changes: the two code bases have diverged too far for a
synchronization to be worth its cost, and the classes have moved to the
`Ekwi\ComposerAttributeCollector` namespace. Read upstream for inspiration, port what is worth
porting by hand, but don't try to keep the trees mergeable.

## Pull Requests

- **Code style** — We're following a [Coding Standard][]. Check the code style with `make lint`.
- **Code health** — We're using [PHPStan][] to analyse the code, with maximum scrutiny. Check the code with `make lint`.
- **Add tests!** — Your contribution won't be accepted if it does not have tests.
- **Document any change in behaviour** — Make sure the `README.md` and any other relevant documentation are kept
  up-to-date.
- **Consider our release cycle** — We follow [SemVer v2.0.0](http://semver.org/). Randomly breaking public APIs is not
  an option.
- **Create feature branches** — We won't pull from your main branch.
- **One pull request per feature** — If you want to do more than one thing, send multiple pull requests.
- **Send coherent history** — Make sure each individual commit in your pull request is meaningful. If you had to make
  multiple intermediate commits while developing, please [squash them][git-squash] before submitting.

## Running Tests

Everything the Makefile runs happens inside the image built from the repository's `Dockerfile`, so a
fresh checkout needs nothing but Docker—no PHP, no Composer, no phpcs on the host. Run `make test`
for the test suite, `make test-filter FILTER=…` for a subset, and `make test-coverage` for a
breakdown of the code coverage; the report is available in `build/coverage/index.html`. `make shell`
opens a session in the image, and `make` on its own lists every target.

The image defaults to PHP 8.4; `make test PHP_VERSION=8.5` builds and uses another one. If you would
rather use a local toolchain, override `PHP_RUN`—`make test PHP_RUN="sh -c"` runs everything
directly on the host, which is what the CI does.

**Thanks for your contribution**!


[Coding Standard]: phpcs.xml
[upstream]: https://github.com/olvlvl/composer-attribute-collector
[git-squash]: http://www.git-scm.com/book/en/v2/Git-Tools-Rewriting-History#Changing-Multiple-Commit-Messages
[PHPStan]: https://phpstan.org/user-guide/getting-started
