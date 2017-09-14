<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

class RsyncCommands extends DrushCommands
{

    /**
     * These are arguments after the aliases and paths have been evaluated.
     * @see validate().
     */
    public $source_evaluated_path;
    public $destination_evaluated_path;

    /**
     * Rsync Drupal code or files to/from another server using ssh.
     *
     * @command core-rsync
     * @param $source A site alias and optional path. See rsync documentation and example.aliases.drushrc.php.
     * @param $destination A site alias and optional path. See rsync documentation and example.aliases.drushrc.php.',
     * @param $extra should be a variable argument once thats working.
     * @optionset_ssh
     * @option exclude-paths List of paths to exclude, seperated by : (Unix-based systems) or ; (Windows).
     * @option include-paths List of paths to include, seperated by : (Unix-based systems) or ; (Windows).
     * @option mode The unary flags to pass to rsync; --mode=rultz implies rsync -rultz.  Default is -akz.
     * @usage drush rsync @dev @stage
     *   Rsync Drupal root from Drush alias dev to the alias stage.
     * @usage drush rsync ./ @stage:%files/img
     *   Rsync all files in the current directory to the 'img' directory in the file storage folder on the Drush alias stage.
     * @usage drush rsync @dev @stage -- --exclude=*.sql --delete
     *   Rsync Drupal root from the Drush alias dev to the alias stage, excluding all .sql files and delete all files on the destination that are no longer on the source.
     * @usage drush rsync @dev @stage --ssh-options="-o StrictHostKeyChecking=no" -- --delete
     *   Customize how rsync connects with remote host via SSH. rsync options like --delete are placed after a --.
     * @aliases rsync
     * @topics docs-aliases
     * @complete \Drush\Commands\CompletionCommands::completeSiteAliases
     */
    public function rsync($source, $destination, array $extra, $options = ['exclude-paths' => null, 'include-paths' => null, 'mode' => 'akz'])
    {
        // Prompt for confirmation. This is destructive.
        if (!drush_get_context('DRUSH_SIMULATE')) {
            drush_print(dt("You will delete files in !target and replace with data from !source", array('!source' => $this->source_evaluated_path, '!target' => $this->destination_evaluated_path)));
            if (!$this->io()->confirm(dt('Do you want to continue?'))) {
                throw new UserAbortException();
            }
        }

        $rsync_options = $this->rsyncOptions($options);
        $parameters = array_merge([$rsync_options], $extra);
        $parameters[] = $this->source_evaluated_path;
        $parameters[] = $this->destination_evaluated_path;

        $ssh_options = $options['ssh-options'];
        $exec = "rsync -e 'ssh $ssh_options'". ' '. implode(' ', array_filter($parameters));
        $exec_result = drush_op_system($exec);

        if ($exec_result == 0) {
            drush_backend_set_result($this->destination_evaluated_path);
        } else {
            throw new \Exception(dt("Could not rsync from !source to !dest", array('!source' => $this->source_evaluated_path, '!dest' => $this->destination_evaluated_path)));
        }
    }

    public function rsyncOptions($options)
    {
        $verbose = $paths = '';
        // Process --include-paths and --exclude-paths options the same way
        foreach (array('include', 'exclude') as $include_exclude) {
            // Get the option --include-paths or --exclude-paths and explode to an array of paths
            // that we will translate into an --include or --exclude option to pass to rsync
            $inc_ex_path = explode(PATH_SEPARATOR, @$options[$include_exclude . '-paths']);
            foreach ($inc_ex_path as $one_path_to_inc_ex) {
                if (!empty($one_path_to_inc_ex)) {
                    $paths = ' --' . $include_exclude . '="' . $one_path_to_inc_ex . '"';
                }
            }
        }

        $mode = '-'. $options['mode'];
        if ($this->io()->isVerbose()) {
            $mode .= 'v';
            $verbose = ' --stats --progress';
        }

        return implode(' ', array_filter([$mode, $verbose, $paths]));
    }

    /**
     * Validate that passed aliases are valid.
     *
     * @hook validate core-rsync
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     * @throws \Exception
     * @return void
     */
    public function validate(CommandData $commandData)
    {
        $additional_options = [];
        $destination = $commandData->input()->getArgument('destination');
        $source = $commandData->input()->getArgument('source');
        $destination_site_record = drush_sitealias_evaluate_path($destination, $additional_options, false);
        $source_site_record = drush_sitealias_evaluate_path($source, $additional_options, false);

        if (!isset($source_site_record)) {
            throw new \Exception(dt('Could not evaluate source path !path.', array('!path' => $source)));
        }
        if (!isset($destination_site_record)) {
            throw new \Exception(dt('Could not evaluate destination path !path.', array('!path' => $destination)));
        }
        if (drush_sitealias_is_remote_site($source_site_record) && drush_sitealias_is_remote_site($destination_site_record)) {
            $msg = dt('Cannot specify two remote aliases. Instead, use this form: `drush !source rsync @self !target`. Make sure site alias definitions are available at !source', array('!source' => $source, '!target' => $destination));
            throw new \Exception($msg);
        }

        $this->source_evaluated_path = $source_site_record['evaluated-path'];
        $this->destination_evaluated_path = $destination_site_record['evaluated-path'];
    }

    /**
     * @hook init core-rsync
     */
    public function init()
    {
        // Try to get @self defined when --uri was not provided.
        drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_SITE);
    }
}
