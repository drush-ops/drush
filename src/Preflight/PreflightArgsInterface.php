<?php
namespace Drush\Preflight;

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
     * Return all of the args from the inputs that were NOT processed
     * by the ArgsPreprocessor (anything not listed in optionsWithValues).
     */
    public function args();

    /**
     * Add one argument to the end of the list returned by the `args()` method.
     *
     * @param string $arg One argument
     */
    public function addArg($arg);

    /**
     * Add everything in the provided array to the list returned by `args()`
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
     */
    public function setAlias($alias);
}
