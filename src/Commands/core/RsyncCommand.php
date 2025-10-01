<?php

declare(strict_types=1);

namespace Drush\Commands\core;

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
    const NAME = 'core:rsync';

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
        $parameters[] = Escape::shellArg($sourceEvaluatedPath->fullyQualifiedPathPreservingTrailingSlash());
        $parameters[] = Escape::shellArg($targetEvaluatedPath->fullyQualifiedPath());

        $ssh_options = $this->drushConfig->get('ssh.options', '');
        $exec = "rsync -e 'ssh $ssh_options'" . ' ' . implode(' ', array_filter($parameters));
        $process = $this->processManager->shell($exec);
        $process->run($process->showRealtime());

        if (!$process->isSuccessful()) {
            throw new \Exception(dt("Could not rsync from !source to !dest", ['!source' => $sourceEvaluatedPath->fullyQualifiedPathPreservingTrailingSlash(), '!dest' => $targetEvaluatedPath->fullyQualifiedPath()]));
        }
        return self::SUCCESS;
    }

    public function rsyncOptions($options, OutputInterface $output): string
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
            assert($aliasConfigContext instanceof \Consolidation\Config\Config);
            $aliasConfigContext->combine($aliasRecord->export());
        }

        return $evaluatedPath;
    }
}
