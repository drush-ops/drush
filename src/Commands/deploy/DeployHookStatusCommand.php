<?php

declare(strict_types=1);

namespace Drush\Commands\deploy;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Prints information about pending deploy update hooks.',
)]
#[CLI\FieldLabels(labels: ['module' => 'Module', 'hook' => 'Hook', 'description' => 'Description'])]
#[CLI\DefaultTableFields(fields: ['module', 'hook', 'description'])]
#[CLI\FilterDefaultField(field: 'hook')]
#[CLI\HelpLinks(links: [HelpLinks::Deploy])]
#[CLI\Version(version: '10.6.1')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class DeployHookStatusCommand extends Command
{
    use AutowireTrait;
    use DeployTrait;
    use FormatterTrait;

    public const NAME = 'deploy:hook-status';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $updates = $this->getRegistry()->getPendingUpdateInformation();
        $rows = [];
        foreach ($updates as $module => $update) {
            if (!empty($update['pending'])) {
                foreach ($update['pending'] as $hook => $description) {
                    $rows[] = [
                        'module' => $module,
                        'hook' => $hook,
                        'description' => $description,
                    ];
                }
            }
        }

        return new RowsOfFields($rows);
    }
}
