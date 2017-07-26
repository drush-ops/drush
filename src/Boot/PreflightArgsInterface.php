<?php
namespace Drush\Boot;

/**
 * Storage for arguments preprocessed during preflight.
 */
interface PreflightArgsInterface
{
    public function optionsWithValues();
    public function args();
    public function addArg($arg);
    public function passArgs($args);
    public function alias();
    public function hasAlias();
    public function setAlias($alias);
}
