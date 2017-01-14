<?php
namespace Drush\Commands\config;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;

trait ConfigTrait  {

  use CustomEventAwareTrait;

  /*
   * Collect available storage filters.
   * @see \Drush\Commands\core\ConfigCommands::coreStorageFilters
   */
  public function getStorageFilters($options) {
    $storage_filters = [];
    $handlers = $this->getCustomEventHandlers('config-storage-filters');
    foreach ($handlers as $handler) {
      $storage_filters += $handler($options);
    }
    return $storage_filters;
  }
}
