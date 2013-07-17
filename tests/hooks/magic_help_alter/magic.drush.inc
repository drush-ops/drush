<?php

/**
 * Implement hook_drush_help_alter()
 *
 * @param type $command
 */
function magic_drush_help_alter(&$command) {
  if ($command['command'] = 'version') {
    $command['options']['magic'] = 'Some magic flag';
  }
}
