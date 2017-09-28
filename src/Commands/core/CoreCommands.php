<?php
namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;

class CoreCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{

    use SiteAliasManagerAwareTrait;

    /**
     * All global options.
     *
     * @command core:global-options
     * @hidden
     * @topic
     * @table-style default
     * @field-labels
     *   name: Name
     *   description: Description
     * @default-fields name,description
     * @aliases core-global-options
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function globalOptions($options = ['format' => 'table'])
    {
        $application = Drush::getApplication();
        $def = $application->getDefinition();
        foreach ($def->getOptions() as $key => $value) {
            $rows[] = [
                'name' => '--'. $key,
                'description' => $value->getDescription(),
            ];
        }
        return new RowsOfFields($rows);
    }

    /**
     * Show Drush version.
     *
     * @command version
     * @table-style compact
     * @list-delimiter :
     * @field-labels
     *   drush-version: Drush version
     *
     * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
     *
     */
    public function version($options = ['format' => 'table'])
    {
        return new PropertyList(['drush-version' => Drush::getVersion()]);
    }

    /**
     * Execute a shell command. Usually used with a site alias.
     *
     * Used by shell aliases that start with !.
     *
     * @command core:execute
     * @param $args The shell command to be executed.
     * @option escape Escape parameters before executing them with the shell. Default is escape; use --no-escape to disable.
     * @optionset_proc_build
     * @handle-remote-commands
     * @usage drush core:execute git pull origin rebase -- --no-ff
     *   Retrieve latest code from git
     * @aliases exec,execute,core-execute
     * @topics docs:aliases
     */
    public function execute(array $args, array $options = ['escape' => true])
    {
        $result = true;
        if ($options['escape']) {
            for ($x = 0; $x < count($args); $x++) {
                // escape all args except for command separators.
                if (!in_array($args[$x], array('&&', '||', ';'))) {
                    $args[$x] = drush_escapeshellarg($args[$x]);
                }
            }
        }
        $cmd = implode(' ', $args);
        // If we selected a Drupal site, then cwd to the site root prior to exec
        $cwd = false;
        if ($selected_root = Drush::bootstrapManager()->getRoot()) {
            if (is_dir($selected_root)) {
                $cwd = getcwd();
                drush_op('chdir', $selected_root);
            }
        }

        $aliasRecord = $this->siteAliasManager()->getSelf();
        if ($aliasRecord) {
            $result = $this->executeCmd($aliasRecord, $cmd);
        } else {
            // Must be a local command.
            $result = (drush_shell_proc_open($cmd) == 0);
        }

        // Restore the cwd if we changed it
        if ($cwd) {
            drush_op('chdir', $cwd);
        }

        if (!$result) {
            throw new \Exception(dt("Command !command failed.", array('!command' => $cmd)));
        }
        return $result;
    }

    /**
     * Helper function for drush_core_execute: run one shell command
     */
    protected function executeCmd($site, $cmd)
    {
        if ($site->isRemote()) {
            // Remote, so execute an ssh command with a bash fragment at the end.
            $exec = drush_shell_proc_build($site, $cmd, true);
            return (drush_shell_proc_open($exec) == 0);
        } elseif ($site->hasRoot() && is_dir($site->root())) {
            return (drush_shell_proc_open('cd ' . drush_escapeshellarg($site->root()) . ' && ' . $cmd) == 0);
        }
        return (drush_shell_proc_open($cmd) == 0);
    }
}
