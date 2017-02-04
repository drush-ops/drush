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
    $data = _core_site_status_table($options['project']);

    $result = new PropertyList($data);
    $result->addRendererFunction([$this, 'renderStatusCell']);

    return $result;
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
