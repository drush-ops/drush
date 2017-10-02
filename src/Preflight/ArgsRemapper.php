<?php
namespace Drush\Preflight;

/**
 * Map commandline arguments from one value to anohter during preflight.
 */
class ArgsRemapper
{
    /**
     * ArgsRemapper constructor
     */
    public function __construct($remap, $remove)
    {
        $this->remap = $remap;
        $this->remove = $remove;
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
        foreach ($argv as $arg) {
            $arg = $this->remapArgument($arg);
            if (isset($arg)) {
                $result[] = $arg;
            }
        }
        return $result;
    }

    /**
     * Apply any transformations to a single arg.
     *
     * @param stinrg $arg One arguent to remap
     * @return string The altered argument
     */
    protected function remapArgument($arg)
    {
        if ($this->checkRemoval($arg)) {
            return null;
        }
        return $this->checkRemap($arg);
    }

    /**
     * Check to see if the provided argument should be removed / ignored.
     *
     * @param stinrg $arg One arguent to inspect
     * @return bool
     */
    protected function checkRemoval($arg)
    {
        foreach ($this->remove as $removalCandidate) {
            if ($this->matches($arg, $removalCandidate)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check to see if the provided single arg needs to be remapped. If
     * it does, then the remapping is performed.
     *
     * @param stinrg $arg One arguent to inspect
     * @return string The altered argument
     */
    protected function checkRemap($arg)
    {
        foreach ($this->remap as $from => $to) {
            if ($this->matches($arg, $from)) {
                return $to . substr($arg, strlen($from));
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
