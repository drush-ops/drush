<?php
namespace Drush\Commands\internal;

use Drush\Commands\DrushCommands;

class HelpCommands extends DrushCommands {

  /**
   * @command help
   */
  public function help() {
    echo 'helpc';
  }
}
