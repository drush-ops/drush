# Drush agent guide

Drush is a PHP command-line shell for Drupal. Commands live in `src/Commands/` and `src/Doctor/Commands/`, grouped by domain.
Tests live in `tests/`. Kernel tests run against the Drupal codebase under `sut/`.
Contributor docs: `docs/contribute/CONTRIBUTING.md` and `docs/contribute/unish.md`.

## Setup
- A configured DDEV project ships with the repo. Some useful commands: `ddev start`, `ddev exec`, `ddev composer`. Drush's commands run through Drupal core's `dr`: `ddev exec dr`. `composer sut:si` installs a sqlite site to try them on.
- `composer install` installs Drush and Drupal core into `sut/`. Without it, `vendor/` and `sut/core` are absent and nothing runs.
- `composer.lock` is gitignored on purpose (CI tests against several Drupal versions). Do not commit one.
- Kernel tests need a database. Set `UNISH_DB_URL` as an env var. Sqlite works locally: `sqlite://localhost/:memory:?module=sqlite`.
- DDEV is the supported container setup (`ddev start`, then prefix commands with `ddev exec`). It sets `UNISH_DB_URL` and `DRUPAL_URI` for you.

## Checks before pushing

Run each and report the result. Run via DDEV when desired:

```sh
composer cs              # phpcs, PSR-12 (composer cbf auto-fixes)
composer lint            # php -l over src and tests
vendor/bin/phpstan       # level 4, CI fails on new errors
composer unit            # fast, no database
composer kernel          # Drupal KernelTestBase tests driving commands with CommandTester
```

`composer test` runs lint, all phpunit suites, and cs. `composer rector` applies the configured Rector sets; run it when touching many files.

## Writing commands

- New commands are one class per command, an invokable `class FooCommand` marked `@internal` with `#[AsCommand]`, a `public const string NAME`, constructor injection, and `#[Argument]`/`#[Option]` on `__invoke()`. Register the class in `src/Doctor/DoctorServiceProvider.php`, which adds it to Drupal's container with autowiring. The commands in `src/Commands/queue/` are the model: they validate input inside `__invoke()`, and render output themselves (`--format` via `Drupal\Core\Command\OutputFormat`).
- User-facing strings go through Drupal's `StringTranslationTrait`, as core's console commands do: `(string) $this->t('Deleted @name.', ['@name' => $name])`, and `formatPlural()` for counts. Attribute descriptions stay plain strings because attributes need constant expressions.
- Class names must end in `Command.php`.
- Dependencies come through constructor promotion and autowiring, never `\Drupal::service()` in new code.

## Tests

- Unit: pure functions, no side effects, no Drupal.
- Kernel: extend `Drupal\KernelTests\KernelTestBase`, build the command from `$this->container` services, drive it with `Symfony\Component\Console\Tester\CommandTester`, assert on `getDisplay()` and the exit code. Preferred for new command tests; `tests/kernel/QueueTest.php` is the model. Needs `UNISH_DB_URL` (or `SIMPLETEST_DB`).
- Kernel test classes declare `#[ShareEnvironment]` (core's `Drupal\TestTools\Attribute\ShareEnvironment`): schema, config and fixtures go in `setUpEnvironment()`, which runs once per class, and `resetEnvironment()` undoes what a test method changed. Test methods read ids from the entities they create and must pass alone and in any order. A class that cannot share, such as one that installs modules, declares `#[ShareEnvironment(false)]` with the reason.

## Conventions

- Drush supports Drupal 11.5+ and 12 on PHP 8.3+, so no PHP 8.4 syntax such as `new Foo()->bar()`. Guard core APIs that are newer than 11.5, as `DoctorServiceProvider` does. `src-drupal-compatibility/` holds copies of core classes that older cores lack.
- Public API and configuration are stable across a major. Keep changes backward compatible; deprecate before removing.
- Docs ship with the change. New commands, options, hooks, or attributes get their `docs/*.md` update in the same PR. `docs/commands/` is generated (`composer mk:docs`), not hand-edited.
- Release branches are `15.x`, `13.x`, etc. PRs target `15.x` upstream at drush-ops/drush.
- Comments explain the non-obvious. PSR-12 formatting, `declare(strict_types=1)` in every file.
