<?php

/**
* Output formatter 'html'
*
* @param $data
*
* @param $metadata
*   '' -
*   '' -
*/
class drush_outputformat_html extends drush_outputformat {
  function format($input, $metadata) {
    $input = drush_help_visible($input);
    $global_options_command = drush_global_options_command(TRUE);
    $global_options_rows = drush_format_help_section($global_options_command, 'options');
    ob_start();
    require_once __DIR__ . '/html.tpl.php';
    $return = ob_get_clean();
    return $return;
  }
}

