<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\Config\Config;
use Consolidation\SiteAlias\HostPath;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteProcess\Util\Escape;
use Drush\Attributes as CLI;
use Drush\Backend\BackendPathEvaluator;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Config\ConfigLocator;
use Drush\Config\DrushConfig;
use Drush\Exceptions\UserAbortException;
use Drush\Exec\ExecTrait;
use Drush\SiteAlias\ProcessManager;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Rsync Drupal code or files to/from another server using ssh.',
    aliases: ['rsync', 'core-rsync'],
)]
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
#[CLI\OptionsetSsh]
#[CLI\HelpLinks(links: [HelpLinks::Aliases])]
final class RsyncCommand extends Command
{
    use AutowireTrait;
    use ExecTrait;

    /**
     * These are arguments after the aliases and paths have been evaluated.
     * @see validate().
     */
    const string NAME = 'core:rsync';

    protected BackendPathEvaluator $pathEvaluator;

    public function __construct(
        protected DrushConfig $drushConfig,
        protected readonly ProcessManager $processManager,
        private readonly SiteAliasManagerInterface $siteAliasManager,
    ) {
        parent::__construct();
        $this->pathEvaluator = new BackendPathEvaluator();
    }

    public function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::REQUIRED, 'A site alias and optional path. See rsync documentation and [Site aliases](../site-aliases.md).')
            ->addArgument('target', InputArgument::REQUIRED, 'A site alias and optional path. See rsync documentation and [Site aliases](../site-aliases.md).')
            ->addArgument('extra', InputArgument::IS_ARRAY, 'Additional parameters after the ssh statement.')
            ->addOption('exclude-paths', null, InputOption::VALUE_REQUIRED, 'List of paths to exclude, seperated by : (Unix-based systems) or ; (Windows).')
            ->addOption('include-paths', null, InputOption::VALUE_REQUIRED, 'List of paths to include, seperated by : (Unix-based systems) or ; (Windows).')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'The unary flags to pass to rsync; --mode=rultz implies rsync -rultz.', 'akz')
            ->addusage('rsync @dev @stage')
            ->addusage('rsync ./ @stage:%files/img')
            ->addusage('rsync @live:%private @stage:%private')
            ->addusage('rsync @dev @stage -- --exclude=*.sql --delete')
            ->addusage('rsync @dev @stage --ssh-options="-o StrictHostKeyChecking=no" -- --delete');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceEvaluatedPath = $this->injectAliasPathParameterOptions($input, 'source');
        $targetEvaluatedPath = $this->injectAliasPathParameterOptions($input, 'target');

        if ($sourceEvaluatedPath->isRemote() && $targetEvaluatedPath->isRemote()) {
            $msg = dt("Cannot specify two remote aliases. Instead, use one of the following alternate options:\n\n    `drush {source} rsync @self {target}`\n    `drush {source} rsync @self {fulltarget}\n\nUse the second form if the site alias definitions are not available at {source}.", ['source' => $sourceEvaluatedPath->getSiteAlias()->name(), 'target' => $targetEvaluatedPath->getSiteAlias()->name(), 'fulltarget' => $targetEvaluatedPath->fullyQualifiedPath()]);
            throw new \InvalidArgumentException($msg);
        }

        // Prompt for confirmation. This is destructive.
        if (!$this->drushConfig->simulate()) {
            $replacements = ['!source' => $sourceEvaluatedPath->fullyQualifiedPathPreservingTrailingSlash(), '!target' => $targetEvaluatedPath->fullyQualifiedPath()];
            if (!(new DrushStyle($input, $output))->confirm(dt("Copy new and override existing files at !target. The source is !source?", $replacements))) {
                throw new UserAbortException();
            }
        }

        $rsync_options = $this->rsyncOptions($input->getOptions(), $output);
        $parameters = array_merge([$rsync_options], $input->getArgument('extra'));

        $source_alias = $this->sourceEvaluatedPath->getSiteAlias();
        $target_alias = $this->targetEvaluatedPath->getSiteAlias();
        $remote_kube_alias = $source_alias->has('kubectl.namespace') ? $source_alias : ($target_alias->has('kubectl.namespace') ? $target_alias : null);
        if ($remote_kube_alias) {
            $kubectl_namespace = $remote_kube_alias->get('kubectl.namespace', '');
            $kubectl_container = $remote_kube_alias->get('kubectl.container', '');
            $kubectl_resource = $remote_kube_alias->get('kubectl.resource', '');

            // Build kubectl base command
            $kubectl_base = "kubectl --namespace=$kubectl_namespace";

            // Determine the pod/resource and container to use
            if (!empty($kubectl_resource)) {
                // Resource format is typically "deploy/name" or "pod/name"
                // We can exec directly into a deployment or pod resource
                $resource_target = $kubectl_resource;
                $container_flag = !empty($kubectl_container) ? " -c $kubectl_container" : "";
            } elseif (!empty($kubectl_container)) {
                // If only container is specified, find the first pod and use the container
                $resource_target = "\$(kubectl --namespace=$kubectl_namespace get pods -o jsonpath='{.items[0].metadata.name}')";
                $container_flag = " -c $kubectl_container";
            } else {
                // No resource or container specified, use first pod
                $resource_target = "\$(kubectl --namespace=$kubectl_namespace get pods -o jsonpath='{.items[0].metadata.name}')";
                $container_flag = "";
            }

            // For kubernetes, we can't use rsync directly. Instead, use tar for transfer.
            if ($source_alias->has('kubectl.namespace')) {
                // Source is remote (kubectl), target is local
                $source_path = rtrim($this->sourceEvaluatedPath->fullyQualifiedPathPreservingTrailingSlash(), '/');
                $target_path = rtrim($this->targetEvaluatedPath->fullyQualifiedPath(), '/');
                $source_dir = dirname($source_path);
                $source_file = basename($source_path);
                $target_dir = dirname($target_path);

                // Ensure target directory exists
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }

                $exec = "$kubectl_base exec -i $resource_target$container_flag -- tar cf - -C " . Escape::shellArg($source_dir) . " " . Escape::shellArg($source_file) . " | tar xf - -C " . Escape::shellArg($target_dir);
                $this->logger()->debug("Kubectl rsync command: $exec");
            } elseif ($target_alias->has('kubectl.namespace')) {
                // Source is local, target is remote (kubectl)
                $source_path = rtrim($this->sourceEvaluatedPath->fullyQualifiedPathPreservingTrailingSlash(), '/');
                $target_path = rtrim($this->targetEvaluatedPath->fullyQualifiedPath(), '/');
                $source_dir = dirname($source_path);
                $source_file = basename($source_path);
                $target_dir = dirname($target_path);

                // Build the complete command with proper pipes
                $exec = "$kubectl_base exec -i $resource_target$container_flag -- mkdir -p " . Escape::shellArg($target_dir) . " && " .
                        "tar cf - -C " . Escape::shellArg($source_dir) . " " . Escape::shellArg($source_file) . " | " .
                        "$kubectl_base exec -i $resource_target$container_flag -- tar xf - -C " . Escape::shellArg($target_dir);
                $this->logger()->debug("Kubectl rsync command: $exec");
            } else {
                throw new \Exception("Invalid kubectl configuration for rsync");
            }
        } else {
            $ssh_options = $this->getConfig()->get('ssh.options', '');
            $parameters[] = Escape::shellArg($this->sourceEvaluatedPath->fullyQualifiedPathPreservingTrailingSlash());
            $parameters[] = Escape::shellArg($this->targetEvaluatedPath->fullyQualifiedPath());
            $exec = "rsync -e 'ssh $ssh_options'" . ' ' . implode(' ', array_filter($parameters));
        }

        $process = $this->processManager->shell($exec);
        $process->run($process->showRealtime());

        if (!$process->isSuccessful()) {
            throw new \Exception(dt("Could not rsync from !source to !dest", ['!source' => $sourceEvaluatedPath->fullyQualifiedPathPreservingTrailingSlash(), '!dest' => $targetEvaluatedPath->fullyQualifiedPath()]));
        }
        return self::SUCCESS;
    }

    public function rsyncOptions(array $options, OutputInterface $output): string
    {
        $verbose = $paths = '';
        // Process --include-paths and --exclude-paths options the same way
        foreach (['include', 'exclude'] as $include_exclude) {
            // Get the option --include-paths or --exclude-paths and explode to an array of paths
            // that we will translate into an --include or --exclude option to pass to rsync
            $inc_ex_path = explode(PATH_SEPARATOR, (string) @$options[$include_exclude . '-paths']);
            foreach ($inc_ex_path as $one_path_to_inc_ex) {
                if (!empty($one_path_to_inc_ex)) {
                    $paths .= ' --' . $include_exclude . '="' . $one_path_to_inc_ex . '"';
                }
            }
        }

        $mode = '-' . $options['mode'];
        if ($output->isVerbose()) {
            $mode .= 'v';
            $verbose = ' --stats --progress';
        }

        return implode(' ', array_filter([$mode, $verbose, $paths]));
    }

    protected function injectAliasPathParameterOptions($input, $parameterName)
    {
        // The Drush configuration object is a ConfigOverlay; fetch the alias
        // context, that already has the options et. al. from the
        // site-selection alias ('drush @site rsync ...'), @self.
        $aliasConfigContext = $this->drushConfig->getContext(ConfigLocator::ALIAS_CONTEXT);
        $aliasName = $input->getArgument($parameterName);
        $evaluatedPath = HostPath::create($this->siteAliasManager, $aliasName);
        $this->pathEvaluator->evaluate($evaluatedPath);

        $aliasRecord = $evaluatedPath->getSiteAlias();

        // If the path is remote, then we will also inject the global
        // options into the alias config context so that we pick up
        // things like ssh-options.
        if ($aliasRecord->isRemote()) {
            assert($aliasConfigContext instanceof Config);
            $aliasConfigContext->combine($aliasRecord->export());
        }

        return $evaluatedPath;
    }
}
