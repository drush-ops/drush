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
class PreprocessArgs
{
    /**
     * @var $home Path to user's home directory
     */
    protected $home;

    /**
     * @var $args Remaining arguments not handled by the preprocessor
     */
    protected $args;

    protected $alias;

    protected $root;

    protected $configPath;

    protected $aliasPath;

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
    public function parseArgv($argv)
    {
        // Get rid of path to application
        array_shift($argv);
        $this->parse($argv);
    }

    /**
     * Parse the commandline arguments
     */
    public function parse($args)
    {
        print "Args are:\n";
        var_export($args);
        print "\n";

        while (!empty($args)) {
            $opt = array_shift($args);

            if ($opt == '--') {
                $this->args[] = $opt;
                return $this->passArgs($args);
            }

            $optionMethod = $this->findMethodForOptionWithValues($opt);
            if ($optionMethod) {
                $value = $this->getOptionValue($opt);
                if (!isset($value)) {
                    $value = array_shift($args);
                }
                call_user_func($optionMethod, $value);
            }
            else {
                $this->args[] = $opt;
            }
        }
    }

    protected function optionsWithValues()
    {
        return [
            '-r' => 'setSelectedSite',
            '--root' => 'setSelectedSite',
            '-c' => 'setConfig',
            '--config' => 'setConfigPath',
            '--alias-path' => 'setAliasPath',
        ];
    }

    /**
     * Check to see if '$opt' is one of the options that we record
     * that takes a value.
     */
    protected function findMethodForOptionWithValues($opt)
    {
        if (empty($opt) || ($opt[0] != '-')) {
            return false;
        }

        foreach ($this->optionsWithValues() as $key => $methodName) {
            $result = $this->checkMatchingOption($opt, $key, $methodName);
            if ($result) {
                return $result;
            }
        }

        return false;
    }

    protected function checkMatchingOption($opt, $key, $methodName)
    {
        if ($key != substr($opt, 0, strlen($key))) {
            return false;

        $method = [$this, $methodName];

        if (strlen($key) == strlen($opt)) {
            return [$method, true];
        }

        if ((strlen($key) < strlen($opt)) && ($opt[1] == '-') && ($opt[strlen($key)] == '=')) {
            $value = substr($opt, strlen($key) + 1);
            return [$method, $value];
        }

        return false;
    }

    /**
     * Append the remaining args to our processed args list.
     */
    protected function passArgs($args)
    {
        $this->args = array_merge($this->args, $args);
    }

    public function args()
    {
        return [];
    }

    public function alias()
    {
        return $this->alias;
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    public function selectedSite()
    {
        return $this->root;
    }

    public function setSelectedSite($root)
    {
        $this->root = $root;
    }

    public function configPath()
    {
        return $this->configPath;
    }

    public function setConfigPath($configPath)
    {
        $this->configPath = $configPath;
    }

    public function aliasPath()
    {
        return $this->aliasPath;
    }

    public function setAliasPath(aliasPath)
    {
        $this->aliasPath = $aliasPath;
    }
}
