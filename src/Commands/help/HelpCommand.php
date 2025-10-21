<?php

declare(strict_types=1);

namespace Drush\Commands\help;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Consolidation\AnnotatedCommand\Help\HelpDocument;
use Consolidation\OutputFormatters\FormatterManager;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Display usage details for a command.',
)]
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
#[CLI\Formatter(returnType: HelpDocument::class, defaultFormatter: 'helpcli')]
#[CLI\TableFormat(listDelimiter: null, tableStyle: 'compact', include_field_labels: false)]
#[CLI\HelpLinks(links: [HelpLinks::Readme])]
final class HelpCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    const NAME = 'help';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
        protected readonly BootstrapManager $bootstrapManager,
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->addArgument(name: 'command_name', mode: InputArgument::REQUIRED, description: 'A command name')
            ->addUsage('help user:login --format=xml')
            ->addUsage('help user:login --format=json');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): HelpDocument
    {
        $this->bootstrapManager->bootstrapMax(DrupalBootLevels::FULL);

        $application = $this->getApplication();
        $command = $application->get($input->getArgument('command_name'));
        if ($command instanceof AnnotatedCommand) {
            $command->optionsHook();
        }
        $helpDocument = new DrushHelpDocument($command);

        // This serves as example about how a command can add a custom Formatter.
        $formatter = new HelpCLIFormatter();
        $this->formatterManager->addFormatter('helpcli', $formatter);

        return $helpDocument;
    }
}
