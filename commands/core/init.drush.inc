<?php

/**
 * @file
 *   Set up local Drush configuration.
 */

use Drush\Log\LogLevel;

/**
 * Implementation of hook_drush_command().
 *
 * @return
 *   An associative array describing your command(s).
 */
function init_drush_command() {
  $items['core-init'] = array(
    'description' => 'Enrich the bash startup file with completion and aliases. Copy .drushrc file to ~/.drush',
    'aliases' => array('init'),
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'package' => 'core',
    'global-options' => array('editor', 'bg'),
    'options' => array(
      'edit' => 'Open the new config file in an editor.',
      'add-path' => "Always add Drush to the \$PATH in the user's .bashrc file, even if it is already in the \$PATH. Use --no-add-path to skip updating .bashrc with the Drush \$PATH. Default is to update .bashrc only if Drush is not already in the \$PATH.",
    ),
    'examples' => array(
      'drush core-init --edit' => 'Enrich Bash and open drush config file in editor.',
      'drush core-init --edit --bg' => 'Return to shell prompt as soon as the editor window opens.',
    ),
  );
  return $items;
}

/**
 * Initialize local Drush configuration
 */
function drush_init_core_init() {
  $home = drush_server_home();
  $drush_config_dir = $home . "/.drush";
  $drush_config_file = $drush_config_dir . "/drushrc.php";
  $drush_bashrc = $drush_config_dir . "/drush.bashrc";
  $drush_prompt = $drush_config_dir . "/drush.prompt.sh";
  $drush_complete = $drush_config_dir . "/drush.complete.sh";
  $examples_dir = DRUSH_BASE_PATH . "/examples";
  $example_configuration = $examples_dir . "/example.drushrc.php";
  $example_bashrc = $examples_dir . "/example.bashrc";
  $example_prompt = $examples_dir . "/example.prompt.sh";
  $example_complete = DRUSH_BASE_PATH . "/drush.complete.sh";
  $bashrc_additions = array();

  // Create a ~/.drush directory if it does not yet exist
  if (!is_dir($drush_config_dir)) {
    drush_mkdir($drush_config_dir);
  }

  // If there is no ~/.drush/drushrc.php, then copy the
  // example Drush configuration file here
  if (!is_file($drush_config_file)) {
    copy($example_configuration, $drush_config_file);
    drush_log(dt("Copied example Drush configuration file to !path", array('!path' => $drush_config_file)), LogLevel::OK);
  }

  // If Drush is not in the $PATH, then figure out which
  // path to add so that Drush can be found globally.
  $add_path = drush_get_option('add-path', NULL);
  if ((!drush_which("drush") || $add_path) && ($add_path !== FALSE)) {
    $drush_path = drush_find_path_to_drush($home);
    $drush_path = preg_replace("%^" . preg_quote($home) . "/%", '$HOME/', $drush_path);

    $bashrc_additions["%$drush_path%"] = "\n# Path to Drush, added by 'drush init'.\nexport PATH=\"\$PATH:$drush_path\"\n\n";
  }

  // If there is no ~/.drush/drush.bashrc file, then copy
  // the example bashrc file there
  if (!is_file($drush_bashrc)) {
    copy($example_bashrc, $drush_bashrc);
    $pattern = basename($drush_bashrc);
    $bashrc_additions["%$pattern%"] = "\n# Include Drush bash customizations.". drush_bash_addition($drush_bashrc);
    drush_log(dt("Copied example Drush bash configuration file to !path", array('!path' => $drush_bashrc)), LogLevel::OK);
  }

  // If there is no ~/.drush/drush.complete.sh file, then copy it there
  if (!is_file($drush_complete)) {
    copy($example_complete, $drush_complete);
    $pattern = basename($drush_complete);
    $bashrc_additions["%$pattern%"] = "\n# Include Drush completion.\n". drush_bash_addition($drush_complete);
    drush_log(dt("Copied Drush completion file to !path", array('!path' => $drush_complete)), LogLevel::OK);
  }

  // If there is no ~/.drush/drush.prompt.sh file, then copy
  // the example prompt.sh file here
  if (!is_file($drush_prompt)) {
    copy($example_prompt, $drush_prompt);
    $pattern = basename($drush_prompt);
    $bashrc_additions["%$pattern%"] = "\n# Include Drush prompt customizations.\n". drush_bash_addition($drush_prompt);
    drush_log(dt("Copied example Drush prompt file to !path", array('!path' => $drush_prompt)), LogLevel::OK);
  }

  // Decide whether we want to add our Bash commands to
  // ~/.bashrc or ~/.bash_profile
  $bashrc = drush_init_find_bashrc($home);

  // Modify the user's bashrc file, adding our customizations.
  $bashrc_contents = "";
  if (file_exists($bashrc)) {
    $bashrc_contents = file_get_contents($bashrc);
  }
  $new_bashrc_contents = $bashrc_contents;
  foreach ($bashrc_additions as $pattern => $addition) {
    // Only put in the addition if the pattern does not already
    // exist in the bashrc file.
    if (!preg_match($pattern, $new_bashrc_contents)) {
      $new_bashrc_contents = $new_bashrc_contents . $addition;
    }
  }
  if ($new_bashrc_contents != $bashrc_contents) {
    if (drush_confirm(dt(implode('', $bashrc_additions) . "Append the above code to !file?", array('!file' => $bashrc)))) {
      file_put_contents($bashrc, "\n\n". $new_bashrc_contents);
      drush_log(dt("Updated bash configuration file !path", array('!path' => $bashrc)), LogLevel::OK);
      drush_log(dt("Start a new shell in order to experience the improvements (e.g. `bash`)."), LogLevel::OK);
      if (drush_get_option('edit')) {
        $exec = drush_get_editor();
        drush_shell_exec_interactive($exec, $drush_config_file, $drush_config_file);
      }
    }
    else {
      return drush_user_abort();
    }
  }
  else {
    drush_log(dt('No code added to !path', array('!path' => $bashrc)), LogLevel::OK);
  }
}

/**
 * Determine which .bashrc file is best to use on this platform.
 */
function drush_init_find_bashrc($home) {
  return $home . "/.bashrc";
}

/**
 * Determine where Drush is located, so that we can add
 * that location to the $PATH
 */
function drush_find_path_to_drush($home) {
  // First test: is Drush inside a vendor directory?
  // Does vendor/bin exist?  If so, use that.  We do
  // not have a good way to locate the 'bin' directory
  // if it has been relocated in the composer.json config
  // section.
  if ($vendor_pos = strpos(DRUSH_BASE_PATH, "/vendor/")) {
    $vendor_dir = substr(DRUSH_BASE_PATH, 0, $vendor_pos + 7);
    $vendor_bin = $vendor_dir . '/bin';
    if (is_dir($vendor_bin)) {
      return $vendor_bin;
    }
  }

  // Fallback is to use the directory that Drush is in.
  return DRUSH_BASE_PATH;
}

function drush_bash_addition($file) {
  return <<<EOD

if [ -f "$file" ] ; then
  source $file
fi


EOD;
}
