<?php

namespace Drush\Boot;

/**
 * This is a do-nothing 'Boot' class that is used when there
 * is no site at --root, or when no root is specified.
 *
 * The 'empty' boot must be careful to never change state,
 * in case bootstrap code might later come along and set
 * a site (e.g. in command completion).
 */
class EmptyBoot extends BaseBoot {

  function __construct() {
  }

  function valid_root($path) {
    return FALSE;
  }

  function bootstrap_phases() {
    return array(
      DRUSH_BOOTSTRAP_DRUSH => '_drush_bootstrap_drush',
    );
  }

  function bootstrap_init_phases() {
    return array(DRUSH_BOOTSTRAP_DRUSH);
  }

  function command_defaults() {
    return array(
      // TODO: Historically, commands that do not explicitly specify
      // their bootstrap level default to DRUSH_BOOTSTRAP_DRUPAL_LOGIN.
      // This isn't right any more, but we can't just change this to
      // DRUSH_BOOTSTRAP_DRUSH, or we will start running commands that
      // needed a full bootstrap with no bootstrap, and that won't work.
      // For now, we will continue to force this to 'login'.  Any command
      // that does not declare 'bootstrap' is declaring that it is a Drupal
      // command.
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
    );
  }
}
