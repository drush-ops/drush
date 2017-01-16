<?php
namespace Drush\Commands;

/*
 * Common completion providers. Use them by adding @complete annotation to your method.
 */
class CompletionCommands {

  static public function completeSiteAliases() {
    return array('values' => array_keys(_drush_sitealias_all_list()));
  }
}