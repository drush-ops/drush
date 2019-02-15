<?php
namespace Drush\Preflight;

use Consolidation\Config\ConfigInterface;

/**
 * Storage for arguments preprocessed during preflight.
 */
interface PreflightArgsInterface
{
    /**
     * Return an associative array of '--option' => 'methodName'.
     * The 'option' string should begin with the appropriate number
     * of dashes (one or two, as desired), and should end with a '='
     * if the option requires a value.
     */
    public function optionsWithValues();

    /**
     * Copy any applicable arguments into the provided configuration
     * object, as appropriate.
     *
     * @param ConfigInterface $config The configuration object to inject data into
     */
    public function applyToConfig(ConfigInterface $config);

    /**
     * Return all of the args from the inputs that were NOT processed
     * by the ArgsPreprocessor (anything not listed in optionsWithValues).
     */
    public function args();

    /**
     * Return the path to this application's executable ($argv[0]).
     */
    public function applicationPath();

    /**
     * Return the command name from the runtime args. Note that the
     * command name also exists inside the runtime args, because the
     * runtime args maintain the order of the options relative to the
     * command name (save for those options removed by preflight args).
     */
    public function commandName();

    /**
     * Store the command name, once it is found.
     */
    public function setCommandName($commandName);

    /**
     * Add one argument to the end of the list returned by the `args()` method.
     *
     * @param string $arg One argument
     */
    public function addArg($arg);

    /**
     * Add everything in the provided array to the list returned by `args()`
     *
     * @param $args
     */
    public function passArgs($args);

    /**
     * Return any '@alias' that may have appeared before the argument
     * holding the command name.
     */
    public function alias();

    /**
     * Returns 'true' if an '@alias' was set.
     */
    public function hasAlias();

    /**
     * Set an alias. Should always begin with '@'.
     *
     * @param string $alias The alias name '@site'
     */
    public function setAlias($alias);
}
