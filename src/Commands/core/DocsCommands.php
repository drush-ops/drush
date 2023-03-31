<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Topic commands.
 * Any commandfile may add topics.
 * Us ethe Topic attribute to indicate the command is a topic
 */
final class DocsCommands extends DrushCommands
{
    const README = 'docs:readme';
    const BISECT = 'docs:bisect';
    const CONFIGURATION = 'docs:configuration';
    const HOOKS = 'docs:hooks';
    const CONFIG_EXPORTING = 'docs:config:exporting';
    const OUTPUT_FORMATS_FILTERS = 'docs:output-formats-filters';
    const ALIASES = 'docs:aliases';
    const SCRIPT = 'docs:script';
    const BOOTSTRAP = 'docs:bootstrap';
    const CRON = 'docs:cron';
    const COMMANDS = 'docs:commands';
    const GENERATORS = 'docs:generators';
    const EXAMPLECOMMAND = 'docs:examplecommand';
    const MIGRATE = 'docs:migrate';
    const EXAMPLE_SYNC_VIA_HTTP = 'docs:example-sync-via-http';
    const POLICY = 'docs:policy';
    const DEPLOY = 'docs:deploy';

    /**
     * README.md
     */
    #[CLI\Command(name: self::README, aliases: ['docs-readme'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../README.md')]
    public function readme(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Drush docs for Git Bisect.
     */
    #[CLI\Command(name: self::BISECT, aliases: ['docs-bisect'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../examples/git-bisect.example.sh')]
    public function bisect(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Drush configuration.
     */
    #[CLI\Command(name: self::CONFIGURATION, aliases: ['docs-configuration'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../docs/using-drush-configuration.md')]
    public function config(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Drush hooks.
     */
    #[CLI\Command(name: self::HOOKS, aliases: ['docs-hooks'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../docs/hooks.md')]
    public function hooks(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Drupal config export instructions, including customizing config by environment.
     */
    #[CLI\Command(name: self::CONFIG_EXPORTING, aliases: ['docs-config-exporting'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../docs/config-exporting.md')]
    public function configExport(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Output formatters and filters: control the command output
     */
    #[CLI\Command(name: self::OUTPUT_FORMATS_FILTERS, aliases: ['docs:output'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../docs/output-formats-filters.md')]
    public function outputFormatsFilters(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Creating site aliases for running Drush on remote sites.
     */
    #[CLI\Command(name: self::ALIASES, aliases: ['docs-aliases'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../docs/site-aliases.md')]
    public function siteAliases(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * An example Drush script.
     */
    #[CLI\Command(name: self::SCRIPT, aliases: ['docs-script'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../examples/helloworld.script')]
    public function script(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Bootstrap explanation: how Drush starts up and prepares the Drupal environment.
     */
    #[CLI\Command(name: self::BOOTSTRAP, aliases: ['docs-bootstrap'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../docs/bootstrap.md')]
    public function bootstrap(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Crontab instructions for running your Drupal cron tasks via `drush cron`.
     */
    #[CLI\Command(name: self::CRON, aliases: ['docs-cron'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../docs/cron.md')]
    public function cron(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Instructions on creating your own Drush commands.
     */
    #[CLI\Command(name: self::COMMANDS, aliases: ['docs-commands'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../docs/commands.md')]
    public function commands(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Instructions on creating your own Drush Generators.
     */
    #[CLI\Command(name: self::GENERATORS, aliases: ['docs-generators'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../docs/generators.md')]
    public function generators(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Example Drush command file.
     */
    #[CLI\Command(name: self::EXAMPLECOMMAND, aliases: ['docs-examplecommand'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../examples/Commands/ArtCommands.php')]
    public function exampleCommand(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Defining and running migrations.
     */
    #[CLI\Command(name: self::MIGRATE)]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../docs/migrate.md')]
    public function migrate(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Extend sql-sync to allow transfer of the sql dump file via http.
     */
    #[CLI\Command(name: self::EXAMPLE_SYNC_VIA_HTTP, aliases: ['docs-example-sync-via-http'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../examples/Commands/SyncViaHttpCommands.php')]
    public function syncHttp(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Example policy file.
     */
    #[CLI\Command(name: self::POLICY, aliases: ['docs-policy'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../examples/Commands/PolicyCommands.php')]
    public function policy(): void
    {
        self::printFileTopic($this->commandData);
    }

    /**
     * Deploy command for Drupal.
     */
    #[CLI\Command(name: self::DEPLOY, aliases: ['docs-deploy'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(path: '../../../docs/deploycommand.md')]
    public function deploy(): void
    {
        self::printFileTopic($this->commandData);
    }
}
