<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;

class EditCommands extends DrushCommands {

  /**
   * Edit drushrc, site alias, and Drupal settings.php files.
   *
   * @command core-edit
   * @bootstrap DRUSH_BOOTSTRAP_MAX
   * @param $filter A substring for filtering the list of files. Omit this argument to choose from loaded files.
   * @optionset_get_editor
   * @usage drush core-config
   *   Pick from a list of config/alias/settings files. Open selected in editor.
   * @usage drush --bg core-config
   *   Return to shell prompt as soon as the editor window opens.
   * @usage drush core-config etc
   *   Edit the global configuration file.
   * @usage drush core-config demo.alia
   * Edit a particular alias file.
   * @usage drush core-config sett
   *   Edit settings.php for the current Drupal site.
   * @usage drush core-config --choice=2
   *  Edit the second file in the choice list.
   * @aliases conf, config, core-config
   * @complete \Drush\Commands\core\EditCommands::complete
   */
  public function edit($filter = NULL) {
    $all = $this->load();

    // Apply any filter that was supplied.
    if ($filter) {
      foreach ($all as $key => $file) {
        if (strpos($file, $filter) === FALSE) {
          unset($all[$key]);
        }
      }
    }
    $all = drush_map_assoc(array_values($all));

    $exec = drush_get_editor();
    if (count($all) == 1) {
      $filepath = current($all);
    }
    else {
      $choice = $this->io()->choice(dt("Choose a file to edit"), $all);
      $filepath = $choice;
    }
    return drush_shell_exec_interactive($exec, $filepath, $filepath);
  }

  public function load($headers = TRUE) {
    $php_header = $php = $rcs_header = $rcs = $aliases_header = $aliases = $drupal_header = $drupal = array();
    $php = $this->php_ini_files();
    if (!empty($php)) {
      if ($headers) {
        $php_header = array('phpini' => '-- PHP ini files --');
      }
    }

    $bash = $this->bash_files();
    if (!empty($bash)) {
      if ($headers) {
        $bash_header = array('bash' => '-- Bash files --');
      }
    }

    drush_sitealias_load_all();
    if ($rcs = drush_get_context_options('context-path', TRUE)) {
      if ($headers) {
        $rcs_header = array('drushrc' => '-- Drushrc --');
      }
    }
    if ($aliases = drush_get_context('drush-alias-files')) {
      if ($headers) {
        $aliases_header = array('aliases' => '-- Aliases --');
      }
    }
    if ($site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT')) {
      $drupal[] = realpath($site_root . '/settings.php');
      if (file_exists($site_root . '/settings.local.php')) {
        $drupal[] = realpath($site_root . '/settings.local.php');
      }
      $drupal[] = realpath(DRUPAL_ROOT . '/.htaccess');
      if ($headers) {
        $drupal_header = array('drupal' => '-- Drupal --');
      }
    }
    return array_merge($php_header, $php, $bash_header, $bash, $rcs_header, $rcs, $aliases_header, $aliases, $drupal_header, $drupal);
  }

  static public function php_ini_files() {
    $ini_files = array();
    $ini_files[] = php_ini_loaded_file();
    if ($drush_ini = getenv('DRUSH_INI')) {
      if (file_exists($drush_ini)) {
        $ini_files[] = $drush_ini;
      }
    }
    foreach (array(DRUSH_BASE_PATH, '/etc/drush', drush_server_home() . '/.drush') as $ini_dir) {
      if (file_exists($ini_dir . "/drush.ini")) {
        $ini_files[] = realpath($ini_dir . "/drush.ini");
      }
    }
    return array_unique($ini_files);
  }

  static public function bash_files() {
    $bash_files = array();
    $home = drush_server_home();
    if ($bashrc = self::find_bashrc($home)) {
      $bash_files[] = $bashrc;
    }
    $prompt = $home . '/.drush/drush.prompt.sh';
    if (file_exists($prompt)) {
      $bash_files[] = $prompt;
    }
    return $bash_files;
  }

  /**
   * Determine which .bashrc file is best to use on this platform.
   *
   * TODO: Also exists as InitCommands::findBashrc. Decide on class-based
   * way to share code like this.
   */
  function find_bashrc($home) {
    return $home . "/.bashrc";
  }

  public function complete() {
    return array('values' => $this->load(FALSE));
  }
}

