<?php

namespace Drush\Boot;
/**
 * Defines the interface for a Boot classes.
 * @todo Doc these methods.
 */
interface Boot {
  function bootstrap_and_dispatch();

  function preflight();

  function command_defaults();
}
