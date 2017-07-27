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
    public function __construct()
    {
    }

    /**
     * Parse the argv array.
     *
     * @param string[] $argv Commandline arguments. The first element is
     *   the path to the application, which we will ignore.
     */
    public function parse($argv, PreflightArgsInterface $storage)
    {
        $sawArg = false;

        // Pull off the path to application. Add it to the
        // 'unprocessed' args list.
        $appName = array_shift($argv);
        $storage->addArg($appName);

        $optionsTable = $storage->optionsWithValues();
        while (!empty($argv)) {
            $opt = array_shift($argv);

            if ($opt == '--') {
                $storage->addArg($opt);
                return $storage->passArgs($argv);
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
                if (!isset($value)) {
                    $value = array_shift($argv);
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
     *
     * @param $optionsTable Table of option names and the name of the
     *   method that should be called to process that option.
     * @param $opt The option string to check
     * @return [$methodName, $optionValue]
     */
    protected function findMethodForOptionWithValues($optionsTable, $opt)
    {
        // Skip $opt if it is empty, or if it is not an option.
        if (empty($opt) || ($opt[0] != '-')) {
            return [false, false];
        }

        // Check each entry in the option table in turn; return as soon
        // as there is a match.
        foreach ($optionsTable as $key => $methodName) {
            $result = $this->checkMatchingOption($opt, $key, $methodName);
            if ($result[0]) {
                return $result;
            }
        }

        return [false, false];
    }

    /**
     * Check to see if the provided option matches the entry from the
     * option table.
     *
     * @param $opt The option string to check
     * @param $key The key to test against. Must always start with '-' or
     *   '--'.  If $key ends with '=', then the option must have a value.
     *   Otherwise, it cannot be supplied with a value, and always defaults
     *   to 'true'.
     * @return [$methodName, $optionValue]
     */
    protected function checkMatchingOption($opt, $key, $methodName)
    {
        // Test to see if $key ends in '='; remove the character if present.
        $hasValue = $key[-1] == '=';
        $key = rtrim($key, '=');

        // If $opt does not begin with $key, then it cannot be a match.
        if ($key != substr($opt, 0, strlen($key))) {
            return [false, false];
        }

        // If $key and $opt are exact matches, then return a positive result.
        // The returned $optionValue will be 'null' if the option requires
        // a value; in this case, the value will be provided from the next
        // argument in the calling function. If this option does not take a
        // supplied value, then we set its value to 'true'
        if (strlen($key) == strlen($opt)) {
            return [$methodName, $hasValue ? null: true];
        }

        // If $opt does not take a value, then we will never match options
        // of the form --opt=value
        if (!$hasValue) {
            return [false, false];
        }

        // If $opt is a double-dash option, and it contains an '=', then
        // the option value is everything after the '='.
        if ((strlen($key) < strlen($opt)) && ($opt[1] == '-') && ($opt[strlen($key)] == '=')) {
            $value = substr($opt, strlen($key) + 1);
            return [$methodName, $value];
        }

        return [false, false];
    }
}
