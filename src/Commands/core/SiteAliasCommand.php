<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Show site alias details, or a list of available site aliases.',
    aliases: ['sa']
)]
#[CLI\FilterDefaultField(field: 'id')]
#[CLI\Formatter(returnType: UnstructuredListData::class, defaultFormatter: 'yaml')]
#[CLI\HelpLinks(links: [HelpLinks::Aliases])]
final class SiteAliasCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    const NAME = 'site:alias';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
        private readonly SiteAliasManagerInterface $siteAliasManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('site', InputArgument::OPTIONAL, 'Site alias or site specification.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml', 'yaml')
            ->addUsage('site:alias')
            ->addUsage('site:alias @dev');

        $this->setHelp('Show site alias details, or a list of available site aliases. Use without arguments to list all alias records known to drush. Use with an alias like @dev to print the alias record for that alias.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): UnstructuredListData
    {
        $io = new DrushStyle($input, $output);
        $site = $input->getArgument('site');

        // First check to see if the user provided a specification that matches
        // multiple sites.
        $aliasList = $this->siteAliasManager->getMultiple($site);
        if (is_array($aliasList) && $aliasList !== []) {
            return new UnstructuredListData($this->siteAliasExportList($aliasList));
        }

        // Next check for a specific alias or a site specification.
        $aliasRecord = $this->siteAliasManager->get($site);
        if ($aliasRecord !== false) {
            return new UnstructuredListData([$aliasRecord->name() => $aliasRecord->export()]);
        }

        if ($site) {
            throw new \Exception('Site alias not found.');
        } else {
            $io->success('No site aliases found.');
            return new UnstructuredListData([]);
        }
    }

    protected function siteAliasExportList(array $aliasList): array
    {
        return array_map(
            fn($aliasRecord) => $aliasRecord->export(),
            $aliasList
        );
    }
}
