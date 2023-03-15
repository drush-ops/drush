<?php

declare(strict_types=1);

namespace Drush\Preflight;

use Consolidation\SiteAlias\SiteAliasName;
use Consolidation\SiteAlias\SiteSpecParser;

/**
 * Preprocess commandline arguments.
 *
 * - Record @sitealias, if present
 * - Record a limited number of global options
 *
 * Anything not handled here is processed by Symfony Console.
 */
class ArgsPreprocessor
{
    /** @var SiteSpecParser */
    protected $specParser;
    /** @var ArgsRemapper */
    protected $remapper;

    /**
     * ArgsPreprocessor constructor
     */
    public function __construct()
    {
        $this->specParser = new SiteSpecParser();
    }

    public function setArgsRemapper(ArgsRemapper $remapper): void
    {
        $this->remapper = $remapper;
    }

    /**
     * Parse the argv array.
     *
     * @param string[] $argv
     *   Commandline arguments. The first element is
     *   the path to the application, which we will ignore.
     *   A storage object to hold the arguments we remove
     *   from argv, plus the remaining argv arguments.
     */
    public function parse(array $argv, PreflightArgsInterface $storage)
    {
        $sawArg = false;

        // Pull off the path to application. Add it to the
        // 'unprocessed' args list.
        $appName = array_shift($argv);
        $storage->addArg($appName);

        if ($this->remapper) {
            $argv = $this->remapper->remap($argv);
        }

        $optionsTable = $storage->optionsWithValues();
        while (!empty($argv)) {
            $opt = array_shift($argv);

            if ($opt == '--') {
                $storage->addArg($opt);
                return $storage->passArgs($argv);
            }

            if (!$sawArg && !$storage->hasAlias() && $this->isAliasOrSiteSpec($opt)) {
                $storage->setAlias($opt);
                continue;
            }

            if (isset($opt[0]) && $opt[0] != '-') {
                if (!$sawArg) {
                    $storage->setCommandName($opt);
                }
                $sawArg = true;
            }

            list($methodName, $value, $acceptsValueFromNextArg) = $this->findMethodForOptionWithValues($optionsTable, $opt);
            if ($methodName) {
                if (!isset($value) && $acceptsValueFromNextArg && static::nextCouldBeValue($argv)) {
                    $value = array_shift($argv);
                }
                $method = [$storage, $methodName];
                call_user_func($method, $value);
            } else {
                $storage->addArg($opt);
            }
        }
        return $storage;
    }

    /**
     * nextCouldBeValue determines whether there is a next argument that
     * exists and does not begin with a `-`.
     */
    protected static function nextCouldBeValue($argv): bool
    {
        if (empty($argv)) {
            return false;
        }
        return $argv[0][0] != '-';
    }

    /**
     * Determine whether the provided argument is an alias or
     * a site specification.
     *
     * @param string $arg
     *   Argument to test.
     */
    protected function isAliasOrSiteSpec(string $arg): bool
    {
        if (SiteAliasName::isAliasName($arg)) {
            return true;
        }
        return $this->specParser->validSiteSpec($arg);
    }

    /**
     * Check to see if '$opt' is one of the options that we record
     * that takes a value.
     *
     * @param $optionsTable Table of option names and the name of the
     *   method that should be called to process that option.
     * @param $opt The option string to check
     * @return [$methodName, $optionValue, $acceptsValueFromNextArg]
     */
    protected function findMethodForOptionWithValues(array $optionsTable, string $opt): array
    {
        // Skip $opt if it is empty, or if it is not an option.
        if (empty($opt) || ($opt[0] != '-')) {
            return [false, false, false];
        }

        // Check each entry in the option table in turn; return as soon
        // as there is a match.
        foreach ($optionsTable as $key => $methodName) {
            $result = $this->checkMatchingOption($opt, $key, $methodName);
            if ($result[0]) {
                return $result;
            }
        }

        return [false, false, false];
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
     * @return [$methodName, $optionValue, $acceptsValueFromNextArg]
     */
    protected function checkMatchingOption(string $opt, string $keyParam, string $methodName): array
    {
        // Test to see if $key ends in '='; remove the character if present.
        // If the char is removed, it means the option accepts a value.
        $key = rtrim($keyParam, '=~');
        $acceptsValue = $key != $keyParam;
        $acceptsValueFromNextArg = $keyParam[strlen($keyParam) - 1] != '~';

        // If $opt does not begin with $key, then it cannot be a match.
        if (!str_starts_with($opt, $key)) {
            return [false, false, false];
        }

        // If $key and $opt are exact matches, then return a positive result.
        // The returned $optionValue will be 'null' if the option requires
        // a value; in this case, the value will be provided from the next
        // argument in the calling function. If this option does not take a
        // supplied value, then we set its value to 'true'
        if (strlen($key) === strlen($opt)) {
            return [$methodName, $acceptsValue ? null : true, $acceptsValueFromNextArg];
        }

        // If the option is not an exact match for the key, then the next
        // character in the option after the key name must be an '='. Otherwise,
        // we might confuse `--locale` for `--local`, etc.
        if ($opt[strlen($key)] != '=') {
            return [false, false, false];
        }

        // If $opt does not take a value, then we will ignore
        // of the form --opt=value
        if (!$acceptsValue) {
            // TODO: We could fail with "The "--foo" option does not accept a value." here.
            // It is important that we ignore the value for '--backend', but other options could throw.
            // For now, we just ignore the value if it is there. This only affects --simulate and --local at the moment.
            return [$methodName, true, $acceptsValueFromNextArg];
        }

        // If $opt is a double-dash option, and it contains an '=', then
        // the option value is everything after the '='.
        if ((strlen($key) < strlen($opt)) && ($opt[1] == '-') && ($opt[strlen($key)] == '=')) {
            $value = substr($opt, strlen($key) + 1);
            return [$methodName, $value, false];
        }

        return [false, false, $acceptsValueFromNextArg];
    }
}
