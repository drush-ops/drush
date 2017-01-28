<?php
namespace Drush\Commands;

/*
 * Common completion providers. Use them by adding @complete annotation to your method.
 */
use Drush\Commands\core\SiteCommands;

class CompletionCommands {

  static public function completeSiteAliases() {
    return array('values' => array_keys(SiteCommands::siteAllList()));
  }
}