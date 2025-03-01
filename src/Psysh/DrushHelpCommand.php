<?php

declare(strict_types=1);

namespace Drush\Psysh;

use Drush\Commands\DrushCommands;
use Psy\Command\Command;
use Psy\Command\Command as BaseCommand;
use Psy\Output\ShellOutput;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Help command.
 *
 * Lists available commands, and gives command-specific help when asked nicely.
 *
 * This replaces the PsySH help command to list commands by category.
 */
class DrushHelpCommand extends BaseCommand
{
    /**
     * Label for PsySH commands.
     */
    const PSYSH_CATEGORY = 'PsySH';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
        ->setName('help')
        ->setAliases(['?'])
        ->setDefinition([
        new InputArgument('command_name', InputArgument::OPTIONAL, 'The command name', null),
        ])
        ->setDescription('Show a list of commands. Type `help [foo]` for information about [foo].');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        assert($output instanceof ShellOutput);

        if ($name = $input->getArgument('command_name')) {
            // Help for an individual command.
            /** @var Command $command */
            $command = $this->getApplication()->get($name);
            $output->page($command->asText());
        } else {
            $namespaces = [];

            // List available commands.
            $commands = $this->getApplication()->all();

            // Find the alignment width.
            $width = 0;
            foreach ($commands as $command) {
                $width = max(strlen($command->getName()), $width);
            }
            $width += 2;

            foreach ($commands as $name => $command) {
                if ($name !== $command->getName()) {
                    continue;
                }

                if ($command->getAliases()) {
                    $aliases = sprintf('  <comment>Aliases:</comment> %s', implode(', ', $command->getAliases()));
                } else {
                    $aliases = '';
                }

                $namespace = '';
                if ($command instanceof DrushCommand) {
                    $namespace = $command->getNamespace();
                }

                if (empty($namespace)) {
                    $namespace = static::PSYSH_CATEGORY;
                }

                if (!isset($namespaces[$namespace])) {
                    $namespaces[$namespace] = [];
                }

                $namespaces[$namespace][] = sprintf("    <info>%-{$width}s</info> %s%s", $name, $command->getDescription(), $aliases);
            }

            $messages = [];

            foreach ($namespaces as $namespace => $command_messages) {
                $messages[] = '';
                $messages[] = sprintf('<comment>%s</comment>', OutputFormatter::escape($namespace));
                foreach ($command_messages as $command_message) {
                    $messages[] = $command_message;
                }
            }

            $output->page($messages);
        }
        return DrushCommands::EXIT_SUCCESS;
    }
}
