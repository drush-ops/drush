<?php

/**
 * @file
 * Contains \Drush\Command\CommandInterface.
 */

namespace Drush\Command;

/**
 * An interface for a Drush command class.
 */
interface CommandInterface {

  /**
   * Provides the command name.
   *
   * @return string
   *   The command's name, e.g. "pm-download".
   */
  public function name();

  /**
   * Provides a list of shorter names for the command.
   *
   * For example, pm-download may also be called via `drush dl`. If the alias is
   * used, Drush will substitute back in the primary command name, so
   * pm-download will still be used to generate the command hook, etc.
   *
   * @return array
   *   An indexed array of command aliases.
   */
  public function aliases();

  public function description();

  public function arguments();

  public function options();

  public function help();

  public function preValidate();

  public function validate();

  public function preExecute();

  public function execute();

  public function postExecute();

}
