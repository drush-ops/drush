<?php

declare(strict_types=1);

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Symfony\Component\Console\Input\InputOption;

/*
 * Common options providers. Use them by adding their Attribute to your command method.
 */
final class OptionsCommands
{
    const REQ = InputOption::VALUE_REQUIRED;

    /**
     * @deprecated Use \Drush\Attributes\OptionsetGetEditor attribute instead.
     */
    #[CLI\Hook(type: HookManager::OPTION_HOOK, selector: 'optionset_proc_build')]
    #[CLI\Option(name: 'ssh-options', description: 'A string of extra options that will be passed to the ssh command (e.g. <info>-p 100</info>)')]
    #[CLI\Option(name: 'tty', description: 'Create a tty (e.g. to run an interactive program).')]
    public function optionsetProcBuild($options = ['ssh-options' => self::REQ, 'tty' => false]): void
    {
    }

    /**
     * @deprecated Use \Drush\Attributes\OptionsetGetEditor attribute instead.
     */
    #[CLI\Hook(type: HookManager::OPTION_HOOK, selector: 'optionset_get_editor')]
    #[CLI\Option(name: 'editor', description: 'A string of bash which launches user\'s preferred text editor. Defaults to <info>${VISUAL-${EDITOR-vi}}</info>.')]
    #[CLI\Option(name: 'bg', description: 'Launch editor in background process.')]
    public function optionsetGetEditor($options = ['editor' => '', 'bg' => false]): void
    {
    }

    /**
     * @deprecated Use \Drush\Attributes\OptionsetSsh attribute instead.
     */
    #[CLI\Hook(type: HookManager::OPTION_HOOK, selector: 'optionset_ssh')]
    #[CLI\Option(name: 'ssh-options', description: 'A string appended to ssh command during rsync, sql-sync, etc.')]
    public function optionsetSsh($options = ['ssh-options' => self::REQ]): void
    {
    }

    /**
     * @deprecated Use \Drush\Attributes\OptionsetSql attribute instead.
     */
    #[CLI\Hook(type: HookManager::OPTION_HOOK, selector: 'optionset_sql')]
    #[CLI\Option(name: 'database', description: 'The DB connection key if using multiple connections in settings.php.')]
    #[CLI\Option(name: 'db-url', description: 'A Drupal 6 style database URL. For example <info>mysql://root:pass@localhost:port/dbname</info>')]
    #[CLI\Option(name: 'target', description: 'The name of a target within the specified database connection.')]
    #[CLI\Option(name: 'show-passwords', description: 'Show password on the CLI. Useful for debugging.')]
    public function optionsetSql($options = ['database' => 'default', 'target' => 'default', 'db-url' => self::REQ, 'show-passwords' => false]): void
    {
    }

    /**
     * @deprecated Use \Drush\Attributes\OptionsetTableSelection attribute instead.
     */
    #[CLI\Hook(type: HookManager::OPTION_HOOK, selector: 'optionset_table_selection')]
    #[CLI\Option(name: 'skip-tables-key', description: 'A key in the $skip_tables array. @see [Site aliases](../site-aliases.md)')]
    #[CLI\Option(name: 'structure-tables-key', description: 'A key in the $structure_tables array. @see [Site aliases](../site-aliases.md)')]
    #[CLI\Option(name: 'tables-key', description: 'A key in the $tables array.')]
    #[CLI\Option(name: 'skip-tables-list', description: 'A comma-separated list of tables to exclude completely.')]
    #[CLI\Option(name: 'structure-tables-list', description: 'A comma-separated list of tables to include for structure, but not data.')]
    #[CLI\Option(name: 'tables-list', description: 'A comma-separated list of tables to transfer.')]
    public function optionsetTableSelection($options = [
        'skip-tables-key' => self::REQ,
        'structure-tables-key' => self::REQ,
        'tables-key' => self::REQ,
        'skip-tables-list' => self::REQ,
        'structure-tables-list' => self::REQ,
        'tables-list' => self::REQ]): void
    {
    }
}
