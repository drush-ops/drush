<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;

class MakeCommands extends DrushCommands {


  /***
   * @command make-convert
   *
   * @bootstrap DRUSH_BOOTSTRAP_NONE
   * @param string $source Filename of the makefile to convert.
   * @description Convert a legacy makefile into a Composer.json file.
   * @usage drush make-convert example.make > composer.json
   *   Convert example.make to composer.json
   * @usage drush make-convert example.make.yml> composer.json
   *   Convert example.make.yml to composer.json
   */
  public function makeConvert($source) {
    $dest_format = 'composer';

    // Load source data.
    $source_format = pathinfo($source, PATHINFO_EXTENSION);

    if ($source_format == $dest_format) {
      drush_print('The source format cannot be the same as the destination format.');
    }

    // Obtain drush make $info array, converting if necessary.
    switch ($source_format) {
      case 'make':
      case 'yml':
      case 'yaml':
        $info = make_parse_info_file($source);
        break;

      default:
        drush_print("The source file format is supported.");
        // @todo Handle this more gracefully.
        exit();
        break;
    }

    $output = $this->drush_make_convert_make_to_composer($info);
    drush_print($output);
  }

  /**
   * Converts a drush info array to a composer.json array.
   *
   * @param array $info
   *   A drush make info array.
   *
   * @return string
   *   A json encoded composer.json schema object.
   */
  protected function drush_make_convert_make_to_composer($info) {
    $core_major_version = substr($info['core'], 0, 1);
    $core_project_name = $core_major_version == 7 ? 'drupal/drupal' : 'drupal/core';

    // Add default projects.
    $projects = array(
      'composer/installers' => '^1.0.20',
      'cweagans/composer-patches' => '~1.0',
      $core_project_name => str_replace('x', '*', $info['core']),
    );

    $patches = array();

    // Iterate over projects, populating composer-friendly array.
    foreach ($info['projects'] as $project_name => $project) {
      switch ($project['type']) {
        case 'core':
          $project['name'] = 'drupal/core';
          $projects[$project['name']] = str_replace('x', '*', $project['version']);
          break;

        default:
          $project['name'] = "drupal/$project_name";
          $projects[$project['name']] = $this->drush_make_convert_project_to_composer($project, $core_major_version);
          break;
      }

      // Add project patches.
      if (!empty($project['patch'])) {
        foreach($project['patch'] as $key => $patch) {
          $patch_description = "Enter {$project['name']} patch #$key description here";
          $patches[$project['name']][$patch_description] = $patch;
        }
      }
    }

    // Iterate over libraries, populating composer-friendly array.
    if (!empty($info['libraries'])) {
      foreach ($info['libraries'] as $library_name => $library) {
        $library_name = 'Verify project name: ' . $library_name;
        $projects[$library_name] = $this->drush_make_convert_project_to_composer($library);
      }
    }

    $output = array(
      'name' => 'Enter project name here',
      'description' => 'Enter project description here',
      'type' => 'project',
      'repositories' => array(
        // @todo Use D.O packagist instead.
        array('type' => 'composer', 'url' => 'https://packagist.drupal-composer.org'),
      ),
      'require' => $projects,
      'minimum-stability' => 'dev',
      'prefer-stable' => TRUE,
      'extra' => array(
        'installer-paths' => array(
          'core' => array('type:drupal-core'),
          'docroot/modules/contrib/{$name}' => array('type:drupal-module'),
          'docroot/profiles/contrib/{$name}' => array('type:drupal-profile'),
          'docroot/themes/contrib/{$name}' => array('type:drupal-theme'),
          'drush/contrib/{$name}' => array('type:drupal-drush'),
        ),
        'patches' => $patches,
      ),
    );

    $output = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return $output;
  }

  /**
   * Converts a make file project array into a composer project version string.
   *
   * @param array $original_project
   *   A project dependency, as defined in a make file.
   *
   * @param string $core_major_version
   *   The major core version. E.g., 6, 7, 8, etc.
   *
   * @return string
   *   The project version, in composer syntax.
   *
   */
  protected function drush_make_convert_project_to_composer($original_project, $core_major_version = 8) {

    // @todo Refactor this command to use D.O packagist version constraints.

    // Typical specified version with major version "x" removed.
    if (!empty($original_project['version'])) {
      $version = str_replace('x', '0', $original_project['version']);
    }
    // Git branch or revision.
    elseif (!empty($original_project['download'])) {
      switch ($original_project['download']['type']) {
        case 'git':
          if (!empty($original_project['download']['branch'])) {
            // @todo Determine if '0' will always be correct.
            $version = str_replace('x', '0', $original_project['download']['branch']);
          }
          if (!empty($original_project['download']['tag'])) {
            // @todo Determine if '0' will always be correct.
            $version = str_replace('x', '0', $original_project['download']['tag']);
          }
          if (!empty($project['download']['revision'])) {
            $version .= '#' . $original_project['download']['revision'];
          }
          break;

        default:
          $version = 'Enter correct project name and version number';
          break;
      }
    }

    $version = "$core_major_version." . $version;

    return $version;
  }
}