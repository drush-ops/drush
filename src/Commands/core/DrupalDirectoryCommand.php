<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteAlias\HostPath;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drush\Backend\BackendPathEvaluator;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Return the filesystem path for modules/themes and other key folders.',
    aliases: ['dd', 'drupal-directory'],
)]
final class DrupalDirectoryCommand extends Command
{
    use AutowireTrait;

    public const string NAME = 'drupal:directory';

    protected BackendPathEvaluator $pathEvaluator;

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager
    ) {
        parent::__construct();
        $this->pathEvaluator = new BackendPathEvaluator();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::OPTIONAL, 'A module/theme name, or special names like root, files, private, or an <info>alias:path</info> string such as @alias:%files.', 'root')
            ->addOption('local-only', null, InputOption::VALUE_NONE, 'Reject any target that specifies a remote site.')
            ->addUsage('cd $(drush dd devel)')
            ->addUsage('cd $(drush dd)')
            ->addUsage('cd $(drush dd files)')
            ->addUsage('drush dd @alias:%files')
            ->addUsage('edit $(drush dd devel)/devel.module')
            ->setHelp('Navigate into directories, print paths to files directory, or open modules in your editor.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $input->getArgument('target');
        $localOnly = $input->getOption('local-only');

        $path = $this->getPath($target, $localOnly);

        // If getPath() is working right, it will turn
        // %blah into the path to the item referred to by the key 'blah'.
        // If there is no such key, then no replacement is done.  In the
        // case of the dd command, we will consider it an error if
        // any keys are -not- replaced.
        if ($path && (!str_contains($path, '%'))) {
            $output->writeln($path);
            return self::SUCCESS;
        }
        throw new \Exception(dt("Target '{target}' not found.", ['target' => $target]));
    }

    /**
     * Given a target (e.g. @site:%modules), return the evaluated directory path.
     *
     * @param $target
     *   The target to evaluate.  Can be @site or /path or @site:path
     *   or @site:%pathalias, etc. (just like rsync parameters)
     * @param $local_only
     *   When true, fail if the site alias is remote.
     */
    protected function getPath($target = 'root', $local_only = false)
    {
        // In the dd command, if the path does not begin with / or % or @ ett.,
        // then we will assume an implicit "%".
        if (preg_match('#^[a-zA-Z0-9_-]*$#', $target)) {
            $target = "%$target";
        }
        // Set up the evaluated path; fail if --local-only and the site alias is remote
        $evaluatedPath = HostPath::create($this->siteAliasManager, $target);
        if ($local_only && $evaluatedPath->isRemote()) {
            throw new \Exception(dt('{target} was remote, and --local-only was specified', ['target' => $target]));
        }
        $this->pathEvaluator->evaluate($evaluatedPath);
        return $evaluatedPath->fullyQualifiedPath();
    }
}
