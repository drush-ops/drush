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

  function preflight() {
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
    return array();
  }
}
