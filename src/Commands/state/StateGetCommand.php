<?php

declare(strict_types=1);

namespace Drush\Commands\state;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal\Core\State\StateInterface;
use Drush\Attributes\Formatter;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Display a state value.',
    aliases: ['sget', 'state-get']
)]
#[Formatter(returnType: PropertyList::class, defaultFormatter: 'string')]
final class StateGetCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const string NAME = 'state:get';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
        protected StateInterface $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED, 'The key name.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml', 'string')
            ->addUsage('state:get system.cron_last')
            ->addUsage('state:get drupal_css_cache_files --format=yaml');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): PropertyList
    {
        $key = $input->getArgument('key');
        $value = $this->state->get($key);
        return new PropertyList([$key => $value]);
    }
}
