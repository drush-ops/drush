<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Psysh\DrushCommand;
use Drush\Psysh\DrushHelpCommand;
use Drupal\Component\Assertion\Handle;
use Drush\Psysh\Shell;
use Drush\Runtime\Runtime;
use Drush\Utils\FsUtils;
use Psy\Configuration;
use Psy\VersionUpdater\Checker;
use Drush\Boot\DrupalBootLevels;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class CliCommands extends DrushCommands
{
    const DOCS_REPL = 'docs:repl';
    const PHP = 'php:cli';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager
    ) {
        parent::__construct();
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new static(
            $container->get('entity_type.manager')
        );

        return $commandHandler;
    }

    /**
     * Drush's PHP Shell.
     */
    #[CLI\Command(name: self::DOCS_REPL, aliases: ['docs-repl'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../docs/repl.md')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function docs(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Open an interactive shell on a Drupal site.
     */
    #[CLI\Command(name: self::PHP, aliases: ['php,core:cli', 'core-cli'])]
    #[CLI\Option(name: 'version-history', description: 'Use command history based on Drupal version. Default is per site.')]
    #[CLI\Option(name: 'cwd', description: 'A directory to change to before launching the shell. Default is the project root directory')]
    #[CLI\Topics(topics: [self::DOCS_REPL])]
    #[CLI\Usage(name: '$node = Node::load(1)', description: 'Entity classes are available without their namespace. For example, Node::load(1) works instead of Drupal\Node\entity\Node::load(1).')]
    #[CLI\Usage(name: '$paragraph = Paragraph::loadRevision(1)', description: 'Also, a loadRevision static method is made available for easier load of revisions.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function cli(array $options = ['version-history' => false, 'cwd' => self::REQ]): void
    {
        $configuration = new Configuration();

        // Set the Drush specific history file path.
        $configuration->setHistoryFile($this->historyPath($options));

        $configuration->setStartupMessage(
            sprintf(
                '<aside>%s (Drupal %s)</aside>',
                \Drupal::config('system.site')->get('name'),
                \Drupal::VERSION
            )
        );

        // Disable checking for updates. Our dependencies are managed with Composer.
        $configuration->setUpdateCheck(Checker::NEVER);

        $shell = new Shell($configuration);


        // Register the assertion handler so exceptions are thrown instead of errors
        // being triggered. This plays nicer with PsySH.
        Handle::register();
        $shell->setScopeVariables(['container' => \Drupal::getContainer()]);

        // Add our casters to the shell configuration.
        $configuration->addCasters($this->getCasters());

        // Add most Drush commands to the shell.
        $shell->addCommands([new DrushHelpCommand()]);
        $shell->addCommands($this->getDrushCommands());

        $this->makeEntitiesAvailableWithShortClassNames();

        // PsySH will never return control to us, but our shutdown handler will still
        // run after the user presses ^D.  Mark this command as completed to avoid a
        // spurious error message.
        Runtime::setCompleted();

        // Run the terminate event before the shell is run. Otherwise, if the shell
        // is forking processes (the default), any child processes will close the
        // database connection when they are killed. So when we return back to the
        // parent process after, there is no connection. This will be called after the
        // command in preflight still, but the subscriber instances are already
        // created from before. Call terminate() regardless, this is a no-op for all
        // DrupalBoot classes except DrupalBoot8.
        if ($bootstrap = Drush::bootstrap()) {
            $bootstrap->terminate();
        }

        // If the cwd option is passed, lets change the current working directory to wherever
        // the user wants to go before we launch psysh.
        if ($options['cwd']) {
            chdir($options['cwd']);
        }

        $shell->run();
    }

    /**
     * Returns a filtered list of Drush commands used for CLI commands.
     */
    protected function getDrushCommands(): array
    {
        $application = Drush::getApplication();
        $commands = $application->all();

        $ignored_commands = [
            'help',
            self::PHP,
            CliCommands::PHP,
            'php',
            PhpCommands::EVAL,
            'eval',
            'ev',
            PhpCommands::SCRIPT,
            'scr',
        ];
        $php_keywords = $this->getPhpKeywords();

        foreach ($commands as $name => $command) {
            // Ignore some commands that don't make sense inside PsySH, are PHP keywords
            // are hidden, or are aliases.
            if (in_array($name, $ignored_commands) || in_array($name, $php_keywords) || ($name !== $command->getName())) {
                unset($commands[$name]);
            } else {
                $aliases = $command->getAliases();
                // Make sure the command aliases don't contain any PHP keywords.
                if (!empty($aliases)) {
                    $command->setAliases(array_diff($aliases, $php_keywords));
                }
            }
        }

        return array_map(function ($command) {
            return new DrushCommand($command);
        }, $commands);
    }

    /**
     * Returns a mapped array of casters for use in the shell.
     *
     * These are Symfony VarDumper casters.
     * See http://symfony.com/doc/current/components/var_dumper/advanced.html#casters
     * for more information.
     *
     * @return callable[].
     *   An array of caster callbacks keyed by class or interface.
     */
    protected function getCasters(): array
    {
        return [
            \Drupal\Core\Entity\ContentEntityInterface::class => \Drush\Psysh\Caster::castContentEntity(...),
            \Drupal\Core\Field\FieldItemListInterface::class => \Drush\Psysh\Caster::castFieldItemList(...),
            \Drupal\Core\Field\FieldItemInterface::class => \Drush\Psysh\Caster::castFieldItem(...),
            \Drupal\Core\Config\Entity\ConfigEntityInterface::class => \Drush\Psysh\Caster::castConfigEntity(...),
            \Drupal\Core\Config\ConfigBase::class => \Drush\Psysh\Caster::castConfig(...),
            \Drupal\Component\DependencyInjection\Container::class => \Drush\Psysh\Caster::castContainer(...),
            \Drupal\Component\Render\MarkupInterface::class => \Drush\Psysh\Caster::castMarkup(...),
        ];
    }

    /**
     * Returns the file path for the CLI history.
     *
     * This can either be site specific (default) or Drupal version specific.
     *
     * @param array $options
     *
     * @return string.
     */
    protected function historyPath(array $options): string
    {
        $cli_directory = FsUtils::getBackupDirParent();
        $drupal_major_version = Drush::getMajorVersion();

        // If there is no drupal version (and thus no root). Just use the current
        // path.
        // @todo Could use a global file within drush?
        if (!$drupal_major_version) {
            $file_name = 'global-' . md5($this->getConfig()->cwd());
        } elseif ($options['version-history']) {
            // If only the Drupal version is being used for the history.
            $file_name = "drupal-$drupal_major_version";
        } else {
            // If there is an alias, use that in the site specific name. Otherwise,
            // use a hash of the root path.
            $aliasRecord = Drush::aliasManager()->getSelf();

            if ($aliasRecord->name()) {
                $site_suffix = ltrim($aliasRecord->name(), '@');
            } else {
                $drupal_root = Drush::bootstrapManager()->getRoot();
                $site_suffix = md5($drupal_root);
            }

            $file_name = "drupal-site-$site_suffix";
        }

        $full_path = "$cli_directory/$file_name";

        $this->logger()->info(dt('History: @full_path', ['@full_path' => $full_path]));

        return $full_path;
    }

    /**
     * Returns a list of PHP keywords.
     *
     * This will act as a blocklist for command and alias names.
     */
    protected function getPhpKeywords(): array
    {
        return [
        '__halt_compiler',
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'eval',
        'exit',
        'extends',
        'final',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'namespace',
        'new',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        ];
    }

    public function makeEntitiesAvailableWithShortClassNames(): void
    {
        foreach ($this->entityTypeManager->getDefinitions() as $definition) {
            $class = $definition->getClass();
            $reflectionClass = new \ReflectionClass($class);
            $parts = explode('\\', $class);
            $end = end($parts);
            // https://github.com/drush-ops/drush/pull/5729 and https://github.com/drush-ops/drush/issues/5730.
            if ($reflectionClass->isFinal() || class_exists($end)) {
                continue;
            }
            // Make it possible to easily load revisions.
            eval(sprintf('class %s extends %s {
                public static function loadRevision($id) {
                    $entity_type_repository = \Drupal::service("entity_type.repository");
                    $entity_type_manager = \Drupal::entityTypeManager();
                    $storage = $entity_type_manager->getStorage($entity_type_repository->getEntityTypeFromClass(static::class));
                    return $storage->loadRevision($id);
                }
            }', $end, $class));
        }
    }
}
