# Creating Custom Commands

Drush commands are plain [Symfony Console commands](https://symfony.com/doc/current/console.html), run by Drupal's `dr`. A module provides a command by adding a class; there is no Drush base class, and no Drush-specific registration.

## Where commands live

Put each command in its own class under the module's `src/Command` directory:

- File: `modules/custom/example/src/Command/XkcdFetchCommand.php`
- Namespace: `Drupal\example\Command`

Drupal finds every class in that directory that has an `#[AsCommand]` attribute, registers it as a service, and autowires its constructor. The module must be installed, and the container rebuilt (`dr cc container`) after you add a command.

## Writing a command

```php
--8<-- "examples/Commands/XkcdFetchCommand.php"
```

The pieces:

- **The class** is invokable: it has an `__invoke()` method and extends nothing. By convention its name ends in `Command`, and it declares `public const string NAME` holding the command name.
- **`#[AsCommand]`** declares the name, `description`, `aliases`, `usages`, `help`, and `hidden`. Usages are shown after the command name, so write `'123'`, not `'xkcd 123'`.
- **Arguments and options** are `__invoke()` parameters marked `#[Argument]` or `#[Option]`. The parameter type sets the input mode: a parameter with a default is optional, `bool` is a flag, and `array` accepts many values. Use `suggestedValues` for shell completion. Ask for `InputInterface` or `OutputInterface` as parameters when you need them.
- **Return** `Command::SUCCESS` or `Command::FAILURE`, or an integer exit code. Throw an exception to report an error; Console prints it and exits non-zero.
- **User-facing strings** go through `StringTranslationTrait`: `(string) $this->t('Deleted @name.', ['@name' => $name])`.

## Dependency injection

Type-hint services in the constructor. Where a type hint is ambiguous, name the service with Symfony's `#[Autowire(service: 'config.storage')]` on the parameter. See [Dependency Injection](dependency-injection.md).

## Prompting for input

Mark an argument with `#[Ask('Which queue?')]` to prompt when it is missing. For anything more involved, such as autocompletion, add a method marked `#[Interact]`. It runs before `__invoke()`, and only when the command is interactive:

```php
#[Interact]
public function askForName(InputInterface $input, OutputInterface $output): void
{
    if (empty($input->getArgument('config_name'))) {
        $question = new Question((string) $this->t('Choose a configuration'));
        $question->setAutocompleterValues($this->configFactory->listAll());
        $input->setArgument('config_name', (new SymfonyStyle($input, $output))->askQuestion($question));
    }
}
```

## Altering other commands

Listen to Console's events to act before or after another command runs. See [Event Subscribers](subscribers.md).

## Testing

Write a kernel test that builds the command, drives it with Symfony's `CommandTester`, and asserts on `getDisplay()` and the exit code. See [QueueTest](https://github.com/drush-ops/drush/blob/15.x/tests/kernel/QueueTest.php).

## Upgrading from Drush 13

In addition to the table below, see the [drupal.org docs](https://www.drupal.org/docs/develop/drupal-apis/command-line-interface-cli-api/drush-command-porting-guide-to-the-new-dr-drupal-core-cli).

| Drush 13 | Now |
|---|---|
| `src/Drush/Commands/FooCommands.php`, one method per command | `src/Command/FooCommand.php`, one class per command |
| `extends DrushCommands` or `extends Command` | An invokable class with no base class |
| `#[CLI\Command]`, `@command` | `#[AsCommand]` |
| `#[CLI\Argument]`, `#[CLI\Option]`, `configure()` | `#[Argument]`, `#[Option]` on `__invoke()` |
| `#[CLI\Usage]` | `usages:` on `#[AsCommand]` |
| Interact hook, `interact()` | `#[Interact]` method or `#[Ask]` |
| Validate hooks, validation Attributes | Validate inside `__invoke()` and throw |
| `RowsOfFields`, `FormatterTrait` | `OutputFormat` option, render output yourself |
| `dt()` | `$this->t()` |
| `$this->logger()`, `$this->io()` | Inject `LoggerInterface`; use `SymfonyStyle` |
| `AutowireTrait`, `create()`, `drush.services.yml` | Constructor autowiring by Drupal's container |
| Site-wide and global commandfiles, `--include` | Commands live in modules |
