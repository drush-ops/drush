<?php

namespace Drush\Boot;

abstract class DrupalBoot extends BaseBoot {

  function __construct() {
  }

  function valid_root($path) {
  }

  function bootstrap_phases() {
    return array(
      DRUSH_BOOTSTRAP_DRUSH                  => '_drush_bootstrap_drush',
      DRUSH_BOOTSTRAP_DRUPAL_ROOT            => '_drush_bootstrap_drupal_root',
      DRUSH_BOOTSTRAP_DRUPAL_SITE            => '_drush_bootstrap_drupal_site',
      DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION   => '_drush_bootstrap_drupal_configuration',
      DRUSH_BOOTSTRAP_DRUPAL_DATABASE        => '_drush_bootstrap_drupal_database',
      DRUSH_BOOTSTRAP_DRUPAL_FULL            => '_drush_bootstrap_drupal_full',
      DRUSH_BOOTSTRAP_DRUPAL_LOGIN           => '_drush_bootstrap_drupal_login');
  }

  function bootstrap_init_phases() {
    return array(DRUSH_BOOTSTRAP_DRUSH, DRUSH_BOOTSTRAP_DRUPAL_FULL);
  }

  function preflight() {
    require_once __DIR__ . '/bootstrap.inc';
    require_once __DIR__ . '/command.inc';
  }

  function enforce_requirement(&$command) {
    parent::enforce_requirement($command);
    drush_enforce_requirement_drupal_dependencies($command);
  }

  function report_command_error($command) {
    // If we reach this point, command doesn't fit requirements or we have not
    // found either a valid or matching command.

    // If no command was found check if it belongs to a disabled module.
    if (!$command) {
      $command = drush_command_belongs_to_disabled_module();
    }
    parent::report_command_error($command);
  }

  function command_defaults() {
    return array(
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
    );
  }
}
