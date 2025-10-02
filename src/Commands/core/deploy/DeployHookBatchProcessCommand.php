<?php

declare(strict_types=1);

namespace Drush\Commands\core\deploy;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Process operations in the specified batch set.',
    hidden: true,
)]
#[CLI\Formatter(returnType: UnstructuredListData::class, defaultFormatter: 'json')]
final class DeployHookBatchProcessCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'deploy:batch-process';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('batch_id', InputArgument::REQUIRED, 'The batch id that will be processed.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): UnstructuredListData
    {
        $batch_id = $input->getArgument('batch_id');
        $result = drush_batch_command($batch_id);
        return new UnstructuredListData($result);
    }
}
