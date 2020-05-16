<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteProcess\ProcessBase;
use Consolidation\SiteProcess\Util\Escape;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Consolidation\SiteAlias\HostPath;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Backend\BackendPathEvaluator;
use Drush\Config\ConfigLocator;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

class RsyncCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * These are arguments after the aliases and paths have been evaluated.
     * @see validate().
     */
    /** @var HostPath */
    public $sourceEvaluatedPath;
    /** @var HostPath */
    public $targetEvaluatedPath;
    /** @var BackendPathEvaluator */
    protected $pathEvaluator;

    public function __construct()
    {
        // TODO: once the BackendInvoke service exists, inject it here
        // and use it to get the path evaluator
        $this->pathEvaluator = new BackendPathEvaluator();
    }

    /**
     * Rsync Drupal code or files to/from another server using ssh.
     *
     * @command core:rsync
     * @param $source A site alias and optional path. See rsync documentation and example.site.yml.
     * @param $target A site alias and optional path. See rsync documentation and example.site.yml.',
     * @param $extra Additional parameters after the ssh statement.
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
     * @aliases rsync,core-rsync
     * @topics docs:aliases
     */
    public function rsync($source, $target, array $extra, $options = ['exclude-paths' => self::REQ, 'include-paths' => self::REQ, 'mode' => 'akz'])
    {
        // Prompt for confirmation. This is destructive.
        if (!$this->getConfig()->simulate()) {
            $replacements = ['!source' => $this->sourceEvaluatedPath->fullyQualifiedPathPreservingTrailingSlash(), '!target' => $this->targetEvaluatedPath->fullyQualifiedPath()];
            if (!$this->io()->confirm(dt("Copy new and override existing files at !target. The source is !source?", $replacements))) {
                throw new UserAbortException();
            }
        }

        $rsync_options = $this->rsyncOptions($options);
        $parameters = array_merge([$rsync_options], $extra);
        $parameters[] = Escape::shellArg($this->sourceEvaluatedPath->fullyQualifiedPathPreservingTrailingSlash());
        $parameters[] = Escape::shellArg($this->targetEvaluatedPath->fullyQualifiedPath());

        $ssh_options = $this->getConfig()->get('ssh.options', '');
        $exec = "rsync -e 'ssh $ssh_options'". ' '. implode(' ', array_filter($parameters));
        $process = $this->processManager()->shell($exec);
        $process->run($process->showRealtime());

        if (!$process->isSuccessful()) {
            throw new \Exception(dt("Could not rsync from !source to !dest", ['!source' => $this->sourceEvaluatedPath->fullyQualifiedPathPreservingTrailingSlash(), '!dest' => $this->targetEvaluatedPath->fullyQualifiedPath()]));
        }
    }

    public function rsyncOptions($options)
    {
        $verbose = $paths = '';
        // Process --include-paths and --exclude-paths options the same way
        foreach (['include', 'exclude'] as $include_exclude) {
            // Get the option --include-paths or --exclude-paths and explode to an array of paths
            // that we will translate into an --include or --exclude option to pass to rsync
            $inc_ex_path = explode(PATH_SEPARATOR, @$options[$include_exclude . '-paths']);
            foreach ($inc_ex_path as $one_path_to_inc_ex) {
                if (!empty($one_path_to_inc_ex)) {
                    $paths .= ' --' . $include_exclude . '="' . $one_path_to_inc_ex . '"';
                }
            }
        }

        $mode = '-'. $options['mode'];
        if ($this->output()->isVerbose()) {
            $mode .= 'v';
            $verbose = ' --stats --progress';
        }

        return implode(' ', array_filter([$mode, $verbose, $paths]));
    }

    /**
     * Evaluate the path aliases in the source and destination
     * parameters. We do this in the pre-command-event so that
     * we can set up the configuration object to include options
     * from the source and target aliases, if any, so that these
     * values may participate in configuration injection.
     *
     * @hook command-event core:rsync
     * @param ConsoleCommandEvent $event
     * @throws \Exception
     * @return void
     */
    public function preCommandEvent(ConsoleCommandEvent $event)
    {
        $input = $event->getInput();
        $this->sourceEvaluatedPath = $this->injectAliasPathParameterOptions($input, 'source');
        $this->targetEvaluatedPath = $this->injectAliasPathParameterOptions($input, 'target');
    }

    protected function injectAliasPathParameterOptions($input, $parameterName)
    {
        // The Drush configuration object is a ConfigOverlay; fetch the alias
        // context, that already has the options et. al. from the
        // site-selection alias ('drush @site rsync ...'), @self.
        $aliasConfigContext = $this->getConfig()->getContext(ConfigLocator::ALIAS_CONTEXT);
        $manager = $this->siteAliasManager();

        $aliasName = $input->getArgument($parameterName);
        $evaluatedPath = HostPath::create($manager, $aliasName);
        $this->pathEvaluator->evaluate($evaluatedPath);

        $aliasRecord = $evaluatedPath->getSiteAlias();

        // If the path is remote, then we will also inject the global
        // options into the alias config context so that we pick up
        // things like ssh-options.
        if ($aliasRecord->isRemote()) {
            $aliasConfigContext->combine($aliasRecord->export());
        }

        return $evaluatedPath;
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
        if ($this->sourceEvaluatedPath->isRemote() && $this->targetEvaluatedPath->isRemote()) {
            $msg = dt("Cannot specify two remote aliases. Instead, use one of the following alternate options:\n\n    `drush {source} rsync @self {target}`\n    `drush {source} rsync @self {fulltarget}\n\nUse the second form if the site alias definitions are not available at {source}.", ['source' => $this->sourceEvaluatedPath->getSiteAlias()->name(), 'target' => $this->targetEvaluatedPath->getSiteAlias()->name(), 'fulltarget' => $this->targetEvaluatedPath->fullyQualifiedPath()]);
            throw new \Exception($msg);
        }
    }
}
