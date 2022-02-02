<?php

namespace Drush\Preflight;

/**
 * Map commandline arguments from one value to another during preflight.
 */
class ArgsRemapper
{
    protected $remapOptions;
    protected $remapCommandAliases;

    /**
     * ArgsRemapper constructor
     */
    public function __construct($remapOptions, $remapCommandAliases)
    {
        $this->remapOptions = $remapOptions;
        $this->remapCommandAliases = $remapCommandAliases;
    }

    /**
     * Given an $argv array, apply all remap operations on each item
     * within it.
     *
     * @param string[] $argv
     */
    public function remap(array $argv): array
    {
        $result = [];
        $sawCommand = false;
        foreach ($argv as $arg) {
            $arg = $this->checkRemap($arg, $sawCommand);
            if (isset($arg)) {
                $result[] = $arg;
            }
        }
        return $result;
    }

    /**
     * Check to see if the provided single arg needs to be remapped. If
     * it does, then the remapping is performed.
     *
     * @param string $arg One argument to inspect
     * @param string $sawCommand True if drush command was found
     * @return string The altered argument
     */
    protected function checkRemap(string $arg, string &$sawCommand)
    {
        if (!$sawCommand && ctype_alpha($arg[0])) {
            $sawCommand = true;
            return $this->remapCommandAlias($arg);
        }
        return $this->remapOptions($arg);
    }

    protected function remapOptions($arg)
    {
        foreach ($this->remapOptions as $from => $to) {
            if ($this->matches($arg, $from)) {
                return $to . substr($arg, strlen($from));
            }
        }
        return $arg;
    }

    protected function remapCommandAlias($arg)
    {
        foreach ($this->remapCommandAliases as $from => $to) {
            if ($arg == $from) {
                return $to;
            }
        }
        return $arg;
    }

    /**
     * Check to see if the provided single arg matches the candidate.
     * If the candidate is `--foo`, then we will match the exact string
     * `--foo`, or the leading substring `--foo=`, and nothing else.
     */
    protected function matches(string $arg, string $candidate): bool
    {
        if (strpos($arg, $candidate) !== 0) {
            return false;
        }

        if (strlen($arg) === strlen($candidate)) {
            return true;
        }

        return $arg[strlen($candidate)] == '=';
    }
}
