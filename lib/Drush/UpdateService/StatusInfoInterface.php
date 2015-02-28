<?php

/**
 * @file
 * Interface for update_status engine implementations.
 */

namespace Drush\UpdateService;

interface StatusInfoInterface {

  /**
   * Constructor.
   * @todo this pertains to a yet to be defined EngineInterface.
   */
  public function __construct($type, $engine, $config);

  /**
   * Returns time of last check of available updates.
   */
  function lastCheck();

  /**
   * Refresh update status information.
   */
  function refresh();

  /**
   * Get update information for all installed projects.
   *
   * @return Array containing remote and local versions
   * for all installed projects.
   */
  function getStatus($projects, $check_disabled);
}
