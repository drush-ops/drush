<?php
namespace Drush\Preflight;

/**
 * Preprocess commandline arguments.
 *
 * - Record @sitealias, if present
 * - Record a limited number of global options
 *
 * Anything not handled here is processed by Symfony Console.
 */
class ArgsRemapper
{
    public function __construct($remap, $remove)
    {
        $this->remap = $remap;
        $this->remove = $remove;
    }

    public function remap($argv)
    {
        $result = [];
        foreach ($argv as $arg) {
            $arg = $this->remapArgument($arg);
            if (isset($arg)) {
                $result[] = $arg;
            }
        }
        return $result;
    }

    protected function remapArgument($arg)
    {
        if ($this->checkRemoval($arg)) {
            return null;
        }
        return $this->checkRemap($arg);
    }

    protected function checkRemoval($arg)
    {
        foreach ($this->remove as $removalCandidate) {
            if ($this->matches($arg, $removalCandidate)) {
                return true;
            }
        }
        return false;
    }

    protected function checkRemap($arg)
    {
        foreach ($this->remap as $from => $to) {
            if ($this->matches($arg, $from)) {
                return $to . substr($arg, strlen($from));
            }
        }
        return $arg;
    }

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
