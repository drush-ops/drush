<?php

/**
 * @file
 * Contains \Drush\Psysh\DrushCommand.
 */

namespace Drush\Psysh;

use Psy\Command\Command as BaseCommand;
use Symfony\Component\Console\Command\Command;
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
     * The currently set subcommand.
     *
     * @var \Symfony\Component\Console\Command\Command
     */
    protected $command;

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
     * Helper for setting a subcommand to retrieve help for.
     *
     * @param \Symfony\Component\Console\Command\Command $command
     */
    public function setCommand(Command $command): void
    {
        $this->command = $command;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        if ($this->command !== null) {
            // Help for an individual command.
            $output->page($this->command->asText());
            $this->command = null;
        } elseif ($name = $input->getArgument('command_name')) {
            // Help for an individual command.
            $output->page($this->getApplication()->get($name)->asText());
        } else {
            $namespaces = [];

            // List available commands.
            $commands = $this->getApplication()->all();

            // Find the alignment width.
            $width = 0;
            foreach ($commands as $command) {
                $width = strlen($command->getName()) > $width ? strlen($command->getName()) : $width;
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

                $namespaces[$namespace][] = sprintf("    <info>%-${width}s</info> %s%s", $name, $command->getDescription(), $aliases);
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
    }
}
