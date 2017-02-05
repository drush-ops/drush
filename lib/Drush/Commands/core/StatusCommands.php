<?php

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;

use Consolidation\OutputFormatters\StructuredData\AssociativeList;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\AnnotatedCommand\CommandData;

class StatusCommands extends DrushCommands {

  /**
   * @command core-status
   * @param $filter
   * @aliases status, st
   *
   * @table-style compact
   * @list-delimiter :
   * @field-labels
   *   drupal-version: Drupal version
   *   uri: Site URI
   *   db-driver: DB driver
   *   db-hostname: DB hostname
   *   db-port: DB port
   *   db-username: DB username
   *   db-password: DB password
   *   db-name: DB name
   *   db-status: Database
   *   bootstrap: Drupal bootstrap
   *   user: Drupal user
   *   theme: Default theme
   *   admin-theme: Admin theme
   *   php-bin: PHP binary
   *   php-conf: PHP config
   *   php-os: PHP OS
   *   drush-script: Drush script
   *   drush-version: Drush version
   *   drush-temp: Drush temp
   *   drush-conf: Drush configs
   *   drush-alias-files: Drush aliases
   *   install-profile: Install profile
   *   root: Drupal root
   *   drupal-settings-file: Drupal Settings
   *   site-path: Site path
   *   site: Site path
   *   themes: Themes path
   *   modules: Modules path
   *   files: Files, Public
   *   private: Files, Private
   *   temp: Files, Temp
   *   config-sync: Drupal config
   *   files-path: Files, Public
   *   temp-path: Files, Temp
   *   %paths: Other paths
   * @default-fields drupal-version,uri,db-driver,db-hostname,db-port,db-username,db-password,db-name,db-status,bootstrap,user,theme,admin-theme,php-bin,php-conf,php-os,drush-script,drush-version,drush-temp,drush-conf,drush-alias-files,install-profile,root,site,files,private,temp,config-sync
   * @pipe-format json
   * @bootstrap DRUSH_BOOTSTRAP_MAX
   * @topics docs-readme
   *
   * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
   */
  public function status($filter = '', $options = ['project' => '', 'format' => 'table', 'fields' => '', 'include-field-labels' => true]) {
    $data = self::getPropertyList($options);

    $result = new PropertyList($data);
    $result->addRendererFunction([$this, 'renderStatusCell']);

    return $result;
  }

  public static function getPropertyList($options) {
    $project = $options['project'];
    $phase = drush_get_context('DRUSH_BOOTSTRAP_PHASE');
    if ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT')) {
      $status_table['drupal-version'] = drush_drupal_version();
      $boot_object = \Drush::bootstrap();
      $conf_dir = $boot_object->conf_path();
      $settings_file = "$conf_dir/settings.php";
      $status_table['drupal-settings-file'] = file_exists($settings_file) ? $settings_file : '';
      if ($site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT')) {
        $status_table['uri'] = drush_get_context('DRUSH_URI');
        try {
          $sql = drush_sql_get_class();
          $db_spec = $sql->db_spec();
          $status_table['db-driver'] = $db_spec['driver'];
          if (!empty($db_spec['unix_socket'])) {
            $status_table['db-socket'] = $db_spec['unix_socket'];
          }
          elseif (isset($db_spec['host'])) {
            $status_table['db-hostname'] = $db_spec['host'];
          }
          $status_table['db-username'] = isset($db_spec['username']) ? $db_spec['username'] : NULL;
          $status_table['db-password'] = isset($db_spec['password']) ? $db_spec['password'] : NULL;
          $status_table['db-name'] = isset($db_spec['database']) ? $db_spec['database'] : NULL;
          $status_table['db-port'] = isset($db_spec['port']) ? $db_spec['port'] : NULL;
          if ($phase > DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION) {
            $status_table['install-profile'] = $boot_object->get_profile();
            if ($phase > DRUSH_BOOTSTRAP_DRUPAL_DATABASE) {
              $status_table['db-status'] = dt('Connected');
              if ($phase > DRUSH_BOOTSTRAP_DRUPAL_FULL) {
                $status_table['bootstrap'] = dt('Successful');
                if ($phase == DRUSH_BOOTSTRAP_DRUPAL_LOGIN) {
                  $status_table['user'] = \Drupal::currentUser()->getAccountName();
                }
              }
            }
          }
        }
        catch (\Exception $e) {
          // Don't worry be happy.
        }
      }
      if (drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
        $status_table['theme'] = drush_theme_get_default();
        $status_table['admin-theme'] = drush_theme_get_admin();
      }
    }
    if ($php_bin = $options['php']) {
      $status_table['php-bin'] = $php_bin;
    }
    $status_table['php-os'] = PHP_OS;
    if ($php_ini_files = EditCommands::php_ini_files()) {
      $status_table['php-conf'] = $php_ini_files;
    }
    $status_table['drush-script'] = DRUSH_COMMAND;
    $status_table['drush-version'] = \Drush::getVersion();
    $status_table['drush-temp'] = drush_find_tmp();
    $status_table['drush-conf'] = drush_flatten_array(drush_get_context_options('context-path', ''));
    $alias_files = _drush_sitealias_find_alias_files();
    $status_table['drush-alias-files'] = $alias_files;

    $paths = _core_path_aliases($project);
    if (!empty($paths)) {
      foreach ($paths as $target => $one_path) {
        $name = $target;
        if (substr($name,0,1) == '%') {
          $name = substr($name,1);
        }
        $status_table[$name] = $one_path;
      }
    }

    // Store the paths into the '%paths' index; this will be
    // used by other code, but will not be included in the output
    // of the drush status command.
    $status_table['%paths'] = $paths;

    return $status_table;
  }

  public function renderStatusCell($key, $cellData, FormatterOptions $options)
  {
      if (is_array($cellData)) {
          return implode("\n", $cellData);
      }
      return $cellData;
  }

  /**
   * @hook pre-command core-status
   */
  public function adjustStatusOptions(CommandData $commandData) {
    $input = $commandData->input();
    $args = $input->getArguments();
    if (!empty($args['filter'])) {
      $input->setOption('fields', '*' . $args['filter'] . '*');
    }
  }
}
