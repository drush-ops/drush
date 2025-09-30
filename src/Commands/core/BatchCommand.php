<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Drush\Attributes as CLI;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Process operations in the specified batch set.',
    aliases: ['batch-process'],
    hidden: true,
)]
#[CLI\Formatter(returnType: UnstructuredListData::class, defaultFormatter: 'json')]
class BatchCommand extends Command
{
    use FormatterTrait;

    const NAME = 'core:batch';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(name: 'batch_id', mode: InputArgument::REQUIRED, description: 'The batch id that will be processed.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input): UnstructuredListData
    {
        $return = drush_batch_command($input->getArgument('batch_id'));
        return new UnstructuredListData($return);
    }
}
