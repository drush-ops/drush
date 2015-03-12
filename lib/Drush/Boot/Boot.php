<?php

namespace Drush\Boot;

/**
 * Defines the interface for a Boot classes.
 * @todo Doc these methods.
 */
interface Boot {
  function valid_root($path);

  function bootstrap_and_dispatch();

  function bootstrap_phases();

  function bootstrap_init_phases();

  function command_defaults();

  function enforce_requirement(&$command);

  function report_command_error($command);
}
