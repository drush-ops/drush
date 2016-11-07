<?php

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\AssociativeList;

class StatusCommands {

  /**
   * @command new-status
   *
   * @field-labels
   *   drupal-version: Drupal version
   *   uri: Site URI
   *   db-driver: Database driver
   *   db-hostname: Database hostname
   *   db-port: Database port
   *   db-username: Database username
   *   db-password: Database password
   *   db-name: Database name
   *   db-status: Database
   *   bootstrap: Drupal bootstrap
   *   user: Drupal user
   *   theme: Default theme
   *   admin-theme: Administration theme
   *   php-bin: PHP executable
   *   php-conf: PHP configuration
   *   php-os: PHP OS
   *   drush-script: Drush script
   *   drush-version: Drush version
   *   drush-temp: Drush temp directory
   *   drush-conf: Drush configuration
   *   drush-alias-files: Drush alias files
   *   install-profile: Install profile
   *   root: Drupal root
   *   drupal-settings-file: Drupal Settings File
   *   site-path: Site path
   *   root: Drupal root
   *   site: Site path
   *   themes: Themes path
   *   modules: Modules path
   *   files: File directory path
   *   private: Private file directory path
   *   temp: Temporary file directory path
   *   config-sync: Sync config path
   *   files-path: File directory path
   *   temp-path: Temporary file directory path
   *   %paths: Other paths
   *
   * @topics docs-readme
   */
  public function status($options = ['project' => '', 'format' => 'table', 'fields' => '']) {
    $data = _core_site_status_table($options['project']);

    return new AssociativeList($data);
  }
}

