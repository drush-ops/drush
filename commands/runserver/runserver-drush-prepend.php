<?php

/**
 * @file
 * Code for exposing Drush as a web service using PHP's built in server.
 */

$drush_args = ltrim($_SERVER['SCRIPT_NAME'], '/');
$drush_executable = trim(shell_exec('which drush'));
$command = sprintf('%s %s --backend', $drush_executable, $drush_args);
error_log(sprintf('Drush Server: Running command %s', $command));
$data = shell_exec($command);
$output = runserver_drush_backend_parse_output($data);
if ($output['error_log']) {
  echo json_encode($output['error_log']);
}
elseif (!$output['output']) {
  echo json_encode(sprintf('Successfully ran the command: !request', $drush_args));
}
else {
  echo $output['output'];
}
return;

/**
 * A slightly less functional copy of drush_backend_parse_output().
 *
 * @see drush_testcase.inc
 */
function runserver_drush_backend_parse_output($string) {
  $regex = sprintf('DRUSH_BACKEND_OUTPUT_START>>>%s<<<DRUSH_BACKEND_OUTPUT_END', '(.*)');
  preg_match("/$regex/s", $string, $match);
  if ($match[1]) {
    // We have our JSON encoded string.
    $output = $match[1];
    // Remove the match we just made and any non printing characters.
    $string = trim(str_replace(sprintf('DRUSH_BACKEND_OUTPUT_START>>>%s<<<DRUSH_BACKEND_OUTPUT_END', $match[1]), '', $string));
  }

  if ($output) {
    $data = json_decode($output, TRUE);
    if (is_array($data)) {
      return $data;
    }
  }
  return $string;
}
