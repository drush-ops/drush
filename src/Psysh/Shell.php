<?php

declare(strict_types=1);

namespace Drush\Psysh;

use Psy\Shell as BaseShell;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\StringInput;

class Shell extends BaseShell
{
    /**
     * Get a command (if one exists) for the current input string.
     */
    protected function getCommand(string $input): ?BaseCommand
    {
        if ($name = $this->getCommandFromInput($input)) {
            return $this->get($name);
        }
        return null;
    }

    /**
     * Check whether a command is set for the current input string.
     *
     *
     * @return bool True if the shell has a command for the given input.
     */
    protected function hasCommand(string $input): bool
    {
        if ($name = $this->getCommandFromInput($input)) {
            return $this->has($name);
        }

        return false;
    }

    /**
     * Get the command from the current input, takes aliases into account.
     *
     * @param string $input
     *   The raw input
     *
     * @return string|NULL
     *   The current command.
     */
    protected function getCommandFromInput(string $input): ?string
    {
        // Remove the alias from the start of the string before parsing and
        // returning the command. Essentially, when choosing a command, we're
        // ignoring the site alias.
        $input = preg_replace('|^\@[^\s]+|', '', $input);

        $input = new StringInput($input);
        return $input->getFirstArgument();
    }
}
