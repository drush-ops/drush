<?php
namespace Drush\Preflight;

/**
 * Map commandline arguments from one value to anohter during preflight.
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
    public function remap($argv)
    {
        $result = [];
        $sawCommmand = false;
        foreach ($argv as $arg) {
            $arg = $this->checkRemap($arg, $sawCommmand);
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
     * @param stinrg $arg One arguent to inspect
     * @return string The altered argument
     */
    protected function checkRemap($arg, &$sawCommmand)
    {
        if (!$sawCommmand && ctype_alpha($arg[0])) {
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
     * `--foo`, or the leading substring `--foo=`, and nohting else.
     * @param string $arg
     * @param string $candidate
     * @return bool
     */
    protected function matches($arg, $candidate)
    {
        if (strpos($arg, $candidate) !== 0) {
            return false;
        }

        if (strlen($arg) == strlen($candidate)) {
            return true;
        }

        return $arg[strlen($candidate)] == '=';
    }
}
