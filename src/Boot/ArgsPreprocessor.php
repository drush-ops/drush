<?php
namespace Drush\Boot;

/**
 * Preprocess commandline arguments.
 *
 * - Record @sitealias, if present
 * - Record a limited number of global options
 *
 * If we are still going to support --php and --php-options flags, then
 * we need to remove those here as well (or add them to the Symfony
 * application).
 */
class ArgsPreprocessor
{
    /**
     * @var $home Path to user's home directory
     */
    protected $home;

    public function __construct($home)
    {
        $this->home = $home;
    }

    /**
     * Parse the argv array.
     *
     * @param string[] $argv Commandline arguments. The first element is
     *   the path to the application, which we will ignore.
     */
    public function parseArgv($argv, PreflightArgsInterface $storage)
    {
        // Get rid of path to application
        array_shift($argv);
        return $this->parse($argv, $storage);
    }

    /**
     * Parse the commandline arguments
     */
    public function parse($args, PreflightArgsInterface $storage)
    {
        $sawArg = false;
        $optionsTable = $storage->optionsWithValues();

        while (!empty($args)) {
            $opt = array_shift($args);

            if ($opt == '--') {
                $storage->addArg($opt);
                return $storage->passArgs($args);
            }

            if ($opt[0] == '@' && !$storage->hasAlias() && !$sawArg) {
                $storage->setAlias($opt);
                continue;
            }

            if ($opt[0] != '-') {
                $sawArg = true;
            }

            list($methodName, $value) = $this->findMethodForOptionWithValues($optionsTable, $opt);
            if ($methodName) {
                if ($value === true) {
                    $value = array_shift($args);
                }
                $method = [$storage, $methodName];
                call_user_func($method, $value);
            }
            else {
                $storage->addArg($opt);
            }
        }
        return $storage;
    }

    /**
     * Check to see if '$opt' is one of the options that we record
     * that takes a value.
     */
    protected function findMethodForOptionWithValues($optionsTable, $opt)
    {
        if (empty($opt) || ($opt[0] != '-')) {
            return [false, false];
        }

        foreach ($optionsTable as $key => $methodName) {
            $result = $this->checkMatchingOption($opt, $key, $methodName);
            if ($result[0]) {
                return $result;
            }
        }

        return [false, false];
    }

    protected function checkMatchingOption($opt, $key, $methodName)
    {
        if ($key != substr($opt, 0, strlen($key))) {
            return [false, false];
        }

        if (strlen($key) == strlen($opt)) {
            return [$methodName, true];
        }

        if ((strlen($key) < strlen($opt)) && ($opt[1] == '-') && ($opt[strlen($key)] == '=')) {
            $value = substr($opt, strlen($key) + 1);
            return [$methodName, $value];
        }

        return [false, false];
    }
}
