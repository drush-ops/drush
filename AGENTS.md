# Drush agent guide

Drush is a PHP command-line shell for Drupal. Commands live in `src/Commands/`, grouped by domain.
Tests ("Unish") live in `tests/` and run against a Drupal site under `sut/` (the System Under Test).
Contributor docs: `docs/contribute/CONTRIBUTING.md` and `docs/contribute/unish.md`.

## Setup
- A configured DDEV project ships with the repo. Some useful commands: `ddev start`, `ddev exec`, `ddev composer`. When running a Drush commands, use `ddev drush`.
- `composer install` installs Drush and Drupal core into `sut/`. Without it, `vendor/` and `sut/core` are absent and nothing runs.
- `composer.lock` is gitignored on purpose (highest/lowest dependency testing). Do not commit one.
- Functional and integration tests need a database. Set `UNISH_DB_URL` as an env var or in `tests/phpunit.xml` (a copy of `phpunit.xml.dist`). Sqlite works locally: `sqlite://localhost/:memory:?module=sqlite`.
- DDEV is the supported container setup (`ddev start`, then prefix commands with `ddev exec`). It sets `UNISH_DB_URL` for you.

## Checks before pushing

Run each and report the result. Run via DDEV when desired:

```sh
composer cs              # phpcs, PSR-12 (composer cbf auto-fixes)
composer lint            # php -l over src, includes, tests
vendor/bin/phpstan       # level 4, CI fails on new errors
composer unit            # fast, no database
composer integration     # shared SUT, must not mutate its database
composer functional      # execs ./drush per test, slow; filter with -- --filter testName
```

`composer test` runs lint, all phpunit suites, and cs. `composer rector` applies the configured Rector sets; run it when touching many files.

## Writing commands

- New commands are one class per command, `final class FooCommand extends Command` with `#[AsCommand]`, a `public const string NAME`, `AutowireTrait` for injection, and Drush attributes from `Drush\Attributes`. `src/Commands/core/CronCommand.php` is a minimal model; `docs/commands.md` documents the options.
- Class names must end in `Command.php`. Plural `*Commands.php` files are the older annotated-command style; extend them only when adding to an existing one.
- Dependencies come through constructor promotion and autowiring, never `\Drupal::service()` in new code.
- `includes/*.inc` is legacy procedural code, excluded from phpcs. Add nothing there.
- `src-symfony-compatibility/` holds shims per Symfony major. Touch it only for Symfony version compatibility.

## Tests

- Unit: pure functions, no side effects, no Drupal.
- Integration: extend `Unish\UnishIntegrationTestCase`; calls the Symfony application directly, Drupal bootstraps once at full level, so argument and option parsing cannot be tested here.
- Functional: extend `Unish\CommandUnishTestCase`; runs the real `./drush` binary in a subprocess, may change SUT state. Mark network-dependent tests `#[Group('slow')]`.
- `UNISH_DIRTY=1` keeps the SUT installed after a run for inspection: `./drush @sut.dev status`.

## Conventions

- Public API and configuration are stable across a major. Keep changes backward compatible; deprecate before removing.
- Docs ship with the change. New commands, options, hooks, or attributes get their `docs/*.md` update in the same PR. `docs/commands/` and `docs/generators/` are generated (`composer mk:docs`), not hand-edited.
- Release branches are `14.x`, `13.x`, etc. PRs target `14.x` upstream at drush-ops/drush.
- Comments explain the non-obvious. PSR-12 formatting, `declare(strict_types=1)` in every file.
