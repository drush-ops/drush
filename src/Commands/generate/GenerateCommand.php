<?php

declare(strict_types=1);

namespace Drush\Commands\generate;

use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Commands\help\ListCommand;
use Drush\Style\DrushStyle;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Generate boilerplate code for modules/plugins/services etc.',
    aliases: ['gen'],
)]
#[CLI\HelpLinks(links: [HelpLinks::Generators])]
final class GenerateCommand extends Command
{
    use AutowireTrait;

    const string NAME = 'generate';

    public function __construct(
        private readonly ContainerInterface $drush_container,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('generator', InputArgument::OPTIONAL, 'A generator name. Omit to pick from available Generators.')
            ->addOption('working-dir', null, InputOption::VALUE_REQUIRED, 'Absolute path to working directory.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Output the generated code but not save it to file system.')
            ->addOption('answer', 'a', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Answer to generator question.')
            ->addOption('destination', null, InputOption::VALUE_REQUIRED, 'Path to a base directory for file writing.')
            ->addUsage('generate drush-command-file')
            ->addUsage('generate controller --answer=Example --answer=example')
            ->addUsage('generate controller -vvv --dry-run');

        $help = 'Drush asks questions so that the generated code is as polished as possible. After
generating, Drush lists the files that were created.

See https://github.com/Chi-teck/drupal-code-generator for a README and bug reports.';
        $this->setHelp($help);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $application = (new ApplicationFactory($this->drush_container, $this->logger))->create();

        $generator = $input->getArgument('generator');
        if (!$generator || $generator === 'list') {
            $all = $application->all();
            unset($all['help'], $all['list'], $all['completion']);
            $namespaced = ListCommand::categorize($all);
            $preamble = dt('Run `drush generate [command]` and answer a few questions in order to write starter code to your project.');
            ListCommand::renderListCLI($application, $namespaced, $output, $preamble);
            return self::SUCCESS;
        }

        // Symfony console app does not provide any way to remove registered commands.
        if ($generator === 'completion') {
            $io->getErrorStyle()->error('Command "completion" is not defined.');
            return Command::FAILURE;
        }

        // Create an isolated input.
        $argv = ['dcg', $generator];

        $argv[] = '--full-path';
        if ($input->getOption('yes')) {
            $argv[] = '--replace';
        }
        if ($input->getOption('working-dir')) {
            $argv[] = '--working-dir=' . $input->getOption('working-dir');
        }
        foreach ($input->getOption('answer') as $answer) {
            $argv[] = '--answer=' . $answer;
        }
        if ($input->getOption('destination')) {
            $argv[] = '--destination=' . $input->getOption('destination');
        }
        if ($input->getOption('ansi')) {
            $argv[] = '--ansi';
        }
        if ($input->getOption('no-ansi')) {
            $argv[] = '--no-ansi';
        }
        if ($input->getOption('dry-run')) {
            $argv[] = '--dry-run';
        }

        return $application->run(new ArgvInput($argv), $output);
    }

    /**
     * Generates completion for generator names.
     */
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('generator')) {
            $application = (new ApplicationFactory($this->drush_container, $this->logger))->create();
            foreach ($application->all() as $name => $command) {
                if ($command->isEnabled() && !$command->isHidden()) {
                    $suggestions->suggestValue($name);
                }
            }
        }
    }
}
