<?php

declare(strict_types=1);

namespace Drush\Commands\updatedb;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Process operations in the specified batch set',
    hidden: true,
)]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
#[CLI\Formatter(returnType: UnstructuredListData::class, defaultFormatter: 'json')]
final class UpdateDbBatchProcessCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const string NAME = 'updatedb:batch-process';

    protected function configure(): void
    {
        $this
            ->addArgument(name: 'batch_id', mode: InputArgument::REQUIRED, description: 'The batch id that will be processed.');
    }

    public function __construct(
        protected BootstrapManager $bootstrapManager,
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Do own bootstrap so we can use the 'update' kernel. Replaces the [#Kernel] attribute.
        $annotationData = new AnnotationData(['kernel' => 'update']);
        $this->bootstrapManager->bootstrapToPhaseIndex(DrupalBootLevels::FULL, $annotationData);

        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): UnstructuredListData
    {
        $result = drush_batch_command($input->getArgument('batch_id'));
        return new UnstructuredListData($result);
    }
}
