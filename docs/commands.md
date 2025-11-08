# Creating Custom Commands

!!! tip

      1. Drush 13+ expects commandfiles to use the [AutowireTrait](https://github.com/drush-ops/drush/blob/13.x/src/Commands/AutowireTrait.php) to inject Drupal and Drush dependencies. Prior versions used a [drush.services.yml file](https://www.drush.org/11.x/dependency-injection/#services-files) which is now deprecated.
      1. Drush 12+ expects all commandfiles in the `<module-name>/src/Drush/<Commands|Generators|Listeners>` directory. The `Drush` subdirectory is a new requirement.

Creating a new Drush command is easy. Follow the steps below.

1. Run `drush generate drush:command-file`.
2. Drush will prompt for the machine name of the module that should _own_ the file. The module selected must already exist and be enabled. Use `drush generate module` to create a new module.
3. Drush will then report that it created a commandfile. Edit as needed.
4. Use the classes for the core Drush commands at [/src/Commands](https://github.com/drush-ops/drush/tree/13.x/src/Commands) as inspiration and documentation.
5. You may [inject dependencies](dependency-injection.md) into a command instance.
6. Write PHPUnit tests based on [Drush Test Traits](https://github.com/drush-ops/drush/blob/13.x/docs/contribute/unish.md#drush-test-traits).

## Four ways to declare a command
The following are supported ways to declare a command.

=== "Console, _Recommended_"

    :warning: Drush 13.7+ is required to use this approach.

    ```php
    namespace Drupal\[module-name]\Drush\Commands;    

    use Consolidation\OutputFormatters\FormatterManager;
    use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
    use Drupal\Core\Template\TwigEnvironment;
    use Drush\Attributes as CLI;
    use Drush\Commands\AutowireTrait;
    use Drush\Formatters\FormatterTrait;
    use Psr\Log\LoggerInterface;
    use Symfony\Component\Console\Attribute\AsCommand;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;    

    #[AsCommand(
        name: self::NAME,
        description: 'Find potentially unused Twig templates.',
        aliases: ['twu'],
    )]
    #[CLI\FieldLabels(labels: ['template' => 'Template', 'compiled' => 'Compiled'])]
    #[CLI\DefaultTableFields(fields: ['template', 'compiled'])]
    #[CLI\FilterDefaultField(field: 'template')]
    #[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
    final class TwigUnusedCommand extends Command
    {
        use AutowireTrait;
        use FormatterTrait;    

        public const NAME = 'twig:unused';    

        public function __construct(
            protected readonly FormatterManager $formatterManager,
            protected readonly TwigEnvironment $twig,
            private readonly LoggerInterface $logger
        ) {
            parent::__construct();
        }    

        protected function configure(): void {
            $this
                ->setHelp('Immediately before running this command, web crawl your entire web site.')
                ->addArgument('searchpaths', InputArgument::REQUIRED, 'A comma delimited list of paths to recursively search.')
                ->addUsage('twig:unused /var/www/mass.local/docroot/modules/custom');
        }    

        public function execute(InputInterface $input, OutputInterface $output): int {
            $data = $this->doExecute($input, $output, $input->getArgument('searchpaths'));
            $this->writeFormattedOutput($input, $output, $data);
            return Command::SUCCESS;
        }    

        public function doExecute(InputInterface $input, OutputInterface $output, string $searchpaths): RowsOfFields
        {
            $this->logger->notice('Found {count} unused', ['count' => count($rows)]);
            return new RowsOfFields($unused);
        }
    ```

=== "Annotated (Attributes), _Deprecated_"

    ```php
    use Drush\Attributes as CLI;

    /**
     * Retrieve and display xkcd cartoons
     */
    #[CLI\Command(name: 'xkcd:fetch', aliases: ['xkcd'])]
    #[CLI\Argument(name: 'search', description: 'Optional argument to retrieve the cartoons matching an index, keyword, or "random".')]
    #[CLI\Option(name: 'image-viewer', description: 'Command to use to view images (e.g. xv, firefox).', suggestedValues: ['open', 'xv', 'firefox'])]
    #[CLI\Option(name: 'google-custom-search-api-key', description: 'Google Custom Search API Key')]
    #[CLI\Usage(name: 'drush xkcd', description: 'Retrieve and display the latest cartoon')]
    #[CLI\Usage(name: 'drush xkcd sandwich', description: 'Retrieve and display cartoons about sandwiches.')]
    public function fetch($search = null, $options = ['image-viewer' => 'open', 'google-custom-search-api-key' => 'AIza']) {
        $this->doFetch($search, $options);
    }
    ```

=== "Annotated Command, _Deprecated_"

    ```php
    /**
     * @command xkcd:fetch
     * @param $search Optional argument to retrieve the cartoons matching an index number, keyword, or "random".
     * @option image-viewer Command to use to view images (e.g. xv, firefox).
     * @option google-custom-search-api-key Google Custom Search API Key.
     * @usage drush xkcd
     *   Retrieve and display the latest cartoon.
     * @usage drush xkcd sandwich
     *   Retrieve and display cartoons about sandwiches.
     * @aliases xkcd
    */
    public function fetch($search = null, $options = ['image-viewer' => 'open', 'google-custom-search-api-key' => 'AIza']) {
        $this->doFetch($search, $options);
    }
    ```

=== "Console (Invokable), Symfony 7.4+"

    ```php
    declare(strict_types=1);
    
    namespace Drupal\woot\Drush\Commands;
    
    use Symfony\Component\Console\Attribute\Argument;
    use Symfony\Component\Console\Attribute\AsCommand;
    use Symfony\Component\Console\Attribute\Option;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Output\OutputInterface;
    
    #[AsCommand(
      name: self::NAME,
      description: 'This command will concatenate two parameters.',
      aliases: ['my-cat'],
      help: 'If the --flip flag is provided, then the result is the concatenation of two and one.',
      usages: ['bet alpha --flip'],
    )]
    final class MyCatCommand {
    
      const NAME = 'my:cat';
    
      public function __invoke(
        OutputInterface $output,
        #[Argument('The first parameter.')] string $one,
        #[Argument('The second parameter.')] string $two,
        #[Option('Whether or not the second parameter should come first in the result')] bool $flip = FALSE,
      ): int
      {
        if ($flip) {
          $output->writeln("{$two}{$one}");
        }
        else {
          $output->writeln("{$one}{$two}");
        }
        return Command::SUCCESS;
      }
    }
    ```

Drush 13.7 deprecates Annotated Commands in favor of pure [Symfony Console commands](https://symfony.com/doc/current/console.html). This implies:

- Each command lives in its own class file
- The command class extends `Symfony\Component\Console\Command\Command` directly. The base class `DrushCommands` is deprecated.
- The command class should use Console's `#[AsCommand]` Attribute to declare its name, aliases, and hidden status. The `#[Command]` Attribute is deprecated.
- Options and Arguments moved from Attributes to a `configure()` method on the command class
- User interaction now happens in an `interact()` method on the command class.
- Drush and Drupal services may be autowired. See [Dependency Injection](dependency-injection.md).
- The main logic of the command moves to an execute() method on the command class.
- Commands that wish to offer multiple _output formats_ (yes please!): 
    - See [TwigUnusedCommand](https://www.drush.org/latest/commands/twig_unused/)] or [SqlDumpCommand](https://www.drush.org/latest/commands/sql_dump/) as examples.
    - Implement the [Formatter Attribute](https://github.com/drush-ops/drush/blob/13.x/src/Attributes/Formatter.php).
    - Command class should `use \Drush\Formatters\FormatterTrait`
    - `execute()` is largely boilerplate. See examples above. By convention, do your work in a `doExecute()` method instead.
- Add the following snippet to your project's composer.json. 
```json
"conflict": {
    "drush/drush": "<13.7"
},
```
- [Numerous Optionset and Validate Attributes are provided by Drush core](https://github.com/drush-ops/drush/blob/13.x/src/Attributes). Custom code can supply additional Attributes+Listeners, which any command may choose to use.

## Altering Command Info

Drush command info can be altered from other modules. This is done by creating and registering a command definition listener. Listeners are dispatched once after non-bootstrap commands are instantiated and once again after bootstrap commands are instantiated.

In the module that wants to alter command info, add a class that:

1. The class namespace, relative to base namespace, should be `Drupal\<module-name>\Drush\Listeners` and the class file should be located under the `src/Drush/Listeners` directory.
1. The filename must have a name like FooListener.php. The prefix `Foo` can be whatever string you want. The file must end in `Listener.php`.
1. The class should implement the `#[AsListener]` PHP Attribute.
1. Implement the alteration logic via a `__invoke(ConsoleDefinitionsEvent $event)` method.
1. Along with the alter code, it's recommended to log a debug message explaining what exactly was altered. This makes things easier on others who may need to debug the interaction of the alter code with other modules. Also, it's a good practice to inject the logger in the class constructor.

For an example, see [WootDefinitionListener](https://github.com/drush-ops/drush/blob/13.x/sut/modules/unish/woot/src/Drush/Listeners/WootDefinitionListener.php) provided by the testing 'woot' module.

## Auto-discovered commands (PSR4)

Such commands are auto-discovered by their class PSR4 namespace and class/file name suffix. Drush will auto-discover commands if:

* The commands class is PSR4 auto-loadable.
* The commands class namespace, relative to base namespace, is `Drush\Commands`. For instance, if a Drush command provider third party library maps this PSR4 autoload entry:
  ```json
  "autoload": {
    "psr-4": {
      "My\\Custom\\Library\\": "src"
    }
  }
  ```
  then the Drush global commands class namespace should be `My\Custom\Library\Drush\Commands` and the class file should be located under the `src/Drush/Commands` directory.
* The class and file name ends with `*Commands`, e.g. `FooCommands`.

Auto-discovered commandfiles should declare their Drush version compatibility via a `conflict` directive. For example, a Composer-managed site-wide command that works with both Drush 11 and Drush 12 might contain something similar to the following in its composer.json file:
```json
    "conflict": {
        "drush/drush": "<11.0",
    }
```
Using `require` in place of `conflict` is not recommended.

!!! warning "Symlinked packages"

    While it is good practice to make your custom commands into a Composer package, please beware that symlinked packages (by using the composer repository type [Path](https://getcomposer.org/doc/05-repositories.md#path)) will **not** be discovered by Drush. When in development, it is recommended to [specify your package's](https://github.com/drush-ops/drush/blob/13.x/examples/example.drush.yml#L52-L67) path in your `drush.yml` to have quick access to your commands.

## Site-wide Commands
Commandfiles that are installed in a Drupal site and are not bundled inside a Drupal module are called _site-wide_ commandfiles. Site-wide commands may either be added directly to the Drupal site's repository (e.g. for site-specific policy files), or via `composer require`. See the [examples/Commands](https://github.com/drush-ops/drush/tree/13.x/examples/Commands) folder for examples. In general, it's preferable to use modules to carry your Drush commands.

Here are some examples of valid commandfile names and namespaces:

1. Simple
     - Filename: $PROJECT_ROOT/drush/Commands/ExampleCommands.php
     - Namespace: Drush\Commands
1. Nested in a subdirectory committed to the site's repository
     - Filename: $PROJECT_ROOT/drush/Commands/example/ExampleCommands.php
     - Namespace: Drush\Commands\example
1. Nested in a subdirectory installed via a Composer package
    - Filename: $PROJECT_ROOT/drush/Commands/contrib/dev_modules/ExampleCommands.php
    - Namespace: Drush\Commands\dev_modules

Note: Make sure you do _not_ include `src` in the path to your command. Your command may not be discovered and have additional problems.

If a commandfile is added via a Composer package, then it may declare any dependencies that it may need in its composer.json file. Site-wide commandfiles that are committed directly to a site's repository only have access to the dependencies already available in the site. 

A site-wide commandfile should have tests that run with each (major) version of Drush that is supported. You may model your test suite after the [example drush extension](https://github.com/drush-ops/example-drush-extension) project.

## Global commands discovered by configuration

!!! warning "Deprecation"

    Configuration discovery has been deprecated and will be removed in a future version of Drush. It is recommended that you avoid global Drush commands, and favor site-wide or PSR4 discovered commandfiles instead. If you really need commands that are not part of any Drupal site, consider making a stand-alone script or custom .phar instead. See [ahoy](https://github.com/ahoy-cli/ahoy), [Robo](https://github.com/consolidation/robo) and [g1a/starter](https://github.com/g1a/starter) as potential starting points.

Global commandfiles discoverable by configuration are not supported by default; in order to enable them, you must configure your `drush.yml` configuration file to add an `include` search location.

For example:

```yaml
drush:
  paths:
    include:
      - '${env.home}/.drush/commands'
```      
With this configuration in place, global commands may be placed as described in the Site-Wide Drush Commands section above. Global commandfiles may not declare any dependencies of their own; they may only use those dependencies already available via the autoloader.

!!! tip

    1. The filename must be have a name like Commands/ExampleCommands.php
       1. The prefix `Example` can be whatever string you want.
       1. The file must end in `Commands.php`
        1. The directory above `Commands` must be one of:
            1.  A Folder listed in the 'include' option. Include may be provided via [config](#global-drush-commands) or via CLI.
            1.  ../drush, /drush or /sites/all/drush. These paths are relative to Drupal root.

Xdebug
------------

Drush disables Xdebug by default. This improves performance substantially, because developers are often debugging something other than Drush and they still need to clear caches, import config, etc. There are two equivalent ways to override Drush's disabling of Xdebug:

- Pass the `--xdebug` global option.
- Set an environment variable: `DRUSH_ALLOW_XDEBUG=1 drush [command]`
