<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal\Component\DependencyInjection\Container;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ConfigBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Drush;
use Drush\Formatters\FormatterTrait;
use Drush\Psysh\Caster;
use Drush\Psysh\DrushCommand;
use Drush\Psysh\DrushHelpCommand;
use Drush\Psysh\Shell;
use Drush\Runtime\Runtime;
use Drush\Utils\FsUtils;
use Psy\Configuration;
use Psy\VersionUpdater\Checker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Open an interactive shell on a Drupal site.',
    aliases: ['php', 'core:cli', 'core-cli'],
)]
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'null')]
#[CLI\HelpLinks(links: [HelpLinks::Repl])]
final class CliCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const string NAME = 'php:cli';

    public function __construct(
        protected readonly EntityTypeManagerInterface $entityTypeManager,
        protected readonly EntityTypeRepositoryInterface $entityTypeRepository,
        protected readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        protected readonly FormatterManager $formatterManager,
        protected readonly DrushConfig $drushConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('version-history', null, InputOption::VALUE_NONE, 'Use command history based on Drupal version. Default is per site.')
            ->addOption('cwd', null, InputOption::VALUE_REQUIRED, 'A directory to change to before launching the shell. Default is the project root directory')
            ->addUsage('$node = Node::load(1)')
            ->addUsage('$node = NodeArticle::load(1)')
            ->addUsage('$paragraph = Paragraph::loadRevision(1)')
            ->setHelp('Entity classes are available without their namespace. For example, Node::load(1) works instead of Drupal\Node\entity\Node::load(1). Entity bundles classes are also available without their namespace. For example, NodeArticle::load(1) works instead of Drupal\node_article\entity\NodeArticle::load(1). Also, a loadRevision static method is made available for easier load of revisions.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $configuration = new Configuration();

        // Set the Drush specific history file path.
        $configuration->setHistoryFile($this->historyPath($input));

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
        // @phpstan-ignore if.alwaysTrue
        if ($bootstrap = Drush::bootstrap()) {
            $bootstrap->terminate();
        }

        // If the cwd option is passed, lets change the current working directory to wherever
        // the user wants to go before we launch psysh.
        if ($cwd = $input->getOption('cwd')) {
            chdir($cwd);
        }

        $shell->run();
        return Command::SUCCESS;
    }

    /**
     * Returns a filtered list of Drush commands used for CLI commands.
     */
    private function getDrushCommands(): array
    {
        $application = Drush::getApplication();
        $commands = $application->all();

        $ignored_commands = [
            'help',
            self::NAME,
            'php:cli',
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

        return array_map(fn($command): DrushCommand => new DrushCommand($command), $commands);
    }

    /**
     * Returns a mapped array of casters for use in the shell.
     *
     * These are Symfony VarDumper casters.
     * See http://symfony.com/doc/current/components/var_dumper/advanced.html#casters
     * for more information.
     *
     * @return callable[]
     *   An array of caster callbacks keyed by class or interface.
     */
    private function getCasters(): array
    {
        return [
            ContentEntityInterface::class => Caster::castContentEntity(...),
            FieldItemListInterface::class => Caster::castFieldItemList(...),
            FieldItemInterface::class => Caster::castFieldItem(...),
            ConfigEntityInterface::class => Caster::castConfigEntity(...),
            ConfigBase::class => Caster::castConfig(...),
            Container::class => Caster::castContainer(...),
            MarkupInterface::class => Caster::castMarkup(...),
        ];
    }

    /**
     * Returns the file path for the CLI history.
     *
     * This can either be site-specific (default) or Drupal version specific.
     */
    private function historyPath(InputInterface $input): string
    {
        $cli_directory = FsUtils::getBackupDirParent();
        $drupal_major_version = Drush::getMajorVersion();

        // If there is no drupal version (and thus no root). Just use the current
        // path.
        // @todo Could use a global file within drush?
        if (!$drupal_major_version) {
            $file_name = 'global-' . md5($this->drushConfig->cwd());
        } elseif ($input->getOption('version-history')) {
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

        return "$cli_directory/$file_name";
    }

    /**
     * Returns a list of PHP keywords.
     *
     * This will act as a blocklist for command and alias names.
     */
    private function getPhpKeywords(): array
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
        // The entity type repository stores a map from class name to entity
        // type id, sneak our short classes in there.
        $classNameEntityTypeMapReflection = (new \ReflectionObject($this->entityTypeRepository))->getProperty('classNameEntityTypeMap');
        $classNameEntityTypeMap = $classNameEntityTypeMapReflection->getValue($this->entityTypeRepository);
        foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $definition) {
            $classNameEntityTypeMap = $this->createShortClassForEntityClass($definition->getClass(), $entityTypeId, $classNameEntityTypeMap);
            foreach ($this->entityTypeBundleInfo->getAllBundleInfo() as $bundles) {
                foreach ($bundles as $info) {
                    if (isset($info['class'])) {
                        $classNameEntityTypeMap = $this->createShortClassForEntityClass($info['class'], $entityTypeId, $classNameEntityTypeMap);
                    }
                }
            }
        }
        $classNameEntityTypeMapReflection->setValue($this->entityTypeRepository, $classNameEntityTypeMap);
    }

    public function createShortClassForEntityClass(string $class, string $entityTypeId, array $classNameEntityTypeMap): array
    {
        $reflectionClass = new \ReflectionClass($class);
        $parts = explode('\\', $class);
        $end = end($parts);
        // https://github.com/drush-ops/drush/pull/5729, https://github.com/drush-ops/drush/issues/5730
        // and https://github.com/drush-ops/drush/issues/5899.
        if ($reflectionClass->isFinal() || $reflectionClass->isAbstract() || class_exists($end)) {
            return $classNameEntityTypeMap;
        }
        $classNameEntityTypeMap[$end] = $entityTypeId;
        // Make it possible to easily load revisions.
        eval(sprintf('class %s extends %s {
                public static function loadRevision($id) {
                    $entity_type_repository = \Drupal::service("entity_type.repository");
                    $entity_type_manager = \Drupal::entityTypeManager();
                    $storage = $entity_type_manager->getStorage($entity_type_repository->getEntityTypeFromClass(static::class));
                    return $storage->loadRevision($id);
                }
            }', $end, $class));
        return $classNameEntityTypeMap;
    }
}
