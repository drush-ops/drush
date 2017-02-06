<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;
use Drush\Make\Parser\ParserIni;
use Drush\Make\Parser\ParserYaml;

class MakeCommands extends DrushCommands {

    const MAKE_API = 2;

  /**
   * Convert a legacy makefile into a Composer.json file.
   *
   * @command make-convert
   *
   * @bootstrap DRUSH_BOOTSTRAP_NONE
   * @param string $source Filename of the makefile to convert.
   * @option string $format The destination format. Only 'composer' is supported.
   * @description Convert a legacy makefile into a Composer.json file.
   * @usage drush make-convert example.make --format=composer > composer.json
   *   Convert example.make to composer.json
   * @usage drush make-convert example.make.yml --format=composer > composer.json
   *   Convert example.make.yml to composer.json
   */
  public function makeConvert($source, $options = ['format' => 'composer']) {

    // Load source data.
    $source_format = pathinfo($source, PATHINFO_EXTENSION);

    if ($source_format == $options['format']) {
      drush_print('The source format cannot be the same as the destination format.');
    }

    // Obtain drush make $info array, converting if necessary.
    switch ($source_format) {
      case 'make':
      case 'yml':
      case 'yaml':
        $info = $this->make_parse_info_file($source);
        break;

      default:
        drush_print("The source file format is not supported.");
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
      'cweagans/composer-patches' => '^1.6.0',
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
        'drupal' => array(
          'type' => 'composer', 'url' => 'https://packages.drupal.org/8'
        ),
      ),
      'require' => $projects,
      'minimum-stability' => 'dev',
      'prefer-stable' => TRUE,
      'extra' => array(
        'installer-paths' => array(
          // @see https://github.com/composer/installers/blob/master/src/Composer/Installers/DrupalInstaller.php
          'core' => array('type:drupal-core'),
          'docroot/modules/contrib/{$name}' => array('type:drupal-module'),
          'docroot/modules/custom/{$name}' => array('type:drupal-custom-module'),
          'docroot/themes/contrib/{$name}' => array('type:drupal-theme'),
          'docroot/themes/custom/{$name}' => array('type:drupal-custom-theme'),
          'docroot/profiles/contrib/{$name}' => array('type:drupal-profile'),
          'docroot/libraries/{$name}' => array('type:drupal-library'),
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


    /**
     * Helper function to parse a makefile and prune projects.
     */
    public function make_parse_info_file($makefile) {
        $info = $this->_make_parse_info_file($makefile);

        // Support making just a portion of a make file.
        $include_only = array(
          'projects' => array_filter(drush_get_option_list('projects')),
          'libraries' => array_filter(drush_get_option_list('libraries')),
        );
        $info = $this->make_prune_info_file($info, $include_only);

        if ($info === FALSE || ($info = $this->make_validate_info_file($info)) === FALSE) {
            return FALSE;
        }

        return $info;
    }

    /**
     * Parse makefile recursively.
     */
    public function _make_parse_info_file($makefile, $element = 'includes') {
        if (!($data = $this->make_get_data($makefile))) {
            return drush_set_error('MAKE_INVALID_MAKE_FILE', dt('Invalid or empty make file: !makefile', array('!makefile' => $makefile)));
        }

        // $info['format'] will specify the determined format.
        $info = $this->_make_determine_format($data);

        // Set any allowed options.
        if (!empty($info['options'])) {
            foreach ($info['options'] as $key => $value) {
                if ($this->_make_is_override_allowed($key)) {
                    // n.b. 'custom' context has lower priority than 'cli', so
                    // options entered on the command line will "mask" makefile options.
                    drush_set_option($key, $value, 'custom');
                }
            }
        }

        // Include any makefiles specified on the command line.
        if ($include_makefiles = drush_get_option_list('includes', FALSE)) {
            drush_unset_option('includes'); // Avoid infinite loop.
            $info['includes'] = is_array($info['includes']) ? $info['includes'] : array();
            foreach ($include_makefiles as $include_make) {
                if (!array_search($include_make, $info['includes'])) {
                    $info['includes'][] = $include_make;
                }
            }
        }

        // Override elements with values from makefiles specified on the command line.
        if ($overrides = drush_get_option_list('overrides', FALSE)) {
            drush_unset_option('overrides'); // Avoid infinite loop.
            $info['overrides'] = is_array($info['overrides']) ? $info['overrides'] : array();
            foreach ($overrides as $override) {
                if (!array_search($override, $info['overrides'])) {
                    $info['overrides'][] = $override;
                }
            }
        }

        $info = $this->_make_merge_includes_recursively($info, $makefile);
        $info = $this->_make_merge_includes_recursively($info, $makefile, 'overrides');

        return $info;
    }

    /**
     * Helper function to merge includes recursively.
     */
    function _make_merge_includes_recursively($info, $makefile, $element = 'includes') {
        if (!empty($info[$element])) {
            if (is_array($info[$element])) {
                $includes = array();
                foreach ($info[$element] as $key => $include) {
                    if (!empty($include)) {
                        if (!$include_makefile = $this->_make_get_include_path($include, $makefile)) {
                            return $this->make_error('BUILD_ERROR', dt("Cannot determine include file location: !include", array('!include' => $include)));
                        }

                        if ($element == 'overrides') {
                            $info = array_replace_recursive($info, $this->_make_parse_info_file($include_makefile, $element));
                        }
                        else {
                            $info = array_replace_recursive($this->_make_parse_info_file($include_makefile), $info);
                        }
                        unset($info[$element][$key]);
                        // Move core back to the top of the list, where
                        // make_generate_from_makefile() expects it.
                        if (!empty($info['projects'])) {
                            array_reverse($info['projects']);
                        }
                    }
                }
            }
        }
        // Ensure $info['projects'] is an associative array, so that we can merge
        // includes properly.
        $this->make_normalize_info($info);

        return $info;
    }

    /**
     * Helper function to determine the proper path for an include makefile.
     */
    public function _make_get_include_path($include, $makefile) {
        if (is_array($include) && $include['download']['type'] = 'git') {
            $tmp_dir = $this->make_tmp();
            $this->make_download_git($include['makefile'], $include['download']['type'], $include['download'], $tmp_dir);
            $include_makefile = $tmp_dir . '/' . $include['makefile'];
        }
        elseif (is_string($include)) {
            $include_path = dirname($makefile);
            if ($this->make_valid_url($include, TRUE)) {
                $include_makefile = $include;
            }
            elseif (file_exists($include_path . '/' . $include)) {
                $include_makefile = $include_path . '/' . $include;
            }
            elseif (file_exists($include)) {
                $include_makefile = $include;
            }
            else {
                return$this-> make_error('BUILD_ERROR', dt("Include file missing: !include", array('!include' => $include)));
            }
        }
        else {
            return FALSE;
        }
        return $include_makefile;
    }

    /**
     * Expand shorthand elements, so that we have an associative array.
     */
    public function make_normalize_info(&$info) {
        if (isset($info['projects'])) {
            foreach($info['projects'] as $key => $project) {
                if (is_numeric($key) && is_string($project)) {
                    unset($info['projects'][$key]);
                    $info['projects'][$project] = array(
                      'version' => '',
                    );
                }
                if (is_string($key) && is_numeric($project)) {
                    $info['projects'][$key] = array(
                      'version' => $project,
                    );
                }
            }
        }
    }

    /**
     * Remove entries in the info file in accordance with the options passed in.
     * Entries are either explicitly 'allowed' (with the $include_only parameter) in
     * which case all *other* entries will be excluded.
     *
     * @param array $info
     *   A parsed info file.
     *
     * @param array $include_only
     *   (Optional) Array keyed by entry type (e.g. 'libraries') against an array of
     *   allowed keys for that type. The special value '*' means 'all entries of
     *   this type'. If this parameter is omitted, no entries will be excluded.
     *
     * @return array
     *   The $info array, pruned if necessary.
     */
    public function make_prune_info_file($info, $include_only = array()) {

        // We may get passed FALSE in some cases.
        // Also we cannot prune an empty array, so no point in this code running!
        if (empty($info)) {
            return $info;
        }

        // We will accrue an explanation of our activities here.
        $msg = array();
        $msg['scope'] = dt("Drush make restricted to the following entries:");

        $pruned = FALSE;

        if (count(array_filter($include_only))) {
            $pruned = TRUE;
            foreach ($include_only as $type => $keys) {

                if (!isset($info[$type])) {
                    continue;
                }
                // For translating
                // dt("Projects");
                // dt("Libraries");
                $type_title = dt(ucfirst($type));

                // Handle the special '*' value.
                if (in_array('*', $keys)) {
                    $msg[$type] = dt("!entry_type: <All>", array('!entry_type' => $type_title));
                }

                // Handle a (possibly empty) array of keys to include/exclude.
                else {
                    $info[$type] = array_intersect_key($info[$type], array_fill_keys($keys, 1));
                    unset($msg[$type]);
                    if (!empty($info[$type])) {
                        $msg[$type] = dt("!entry_type: !make_entries", array('!entry_type' => $type_title, '!make_entries' => implode(', ', array_keys($info[$type]))));
                    }
                }
            }
        }

        if ($pruned) {
            // Make it clear to the user what's going on.
            drush_log(implode("\n", $msg), LogLevel::OK);

            // Throw an error if these restrictions reduced the make to nothing.
            if (empty($info['projects']) && empty($info['libraries'])) {
                // This error mentions the options explicitly to make it as clear as
                // possible to the user why this error has occurred.
                $this->make_error('BUILD_ERROR', dt("All projects and libraries have been excluded. Review the 'projects' and 'libraries' options."));
            }
        }

        return $info;
    }

    /**
     * Validate the make file.
     */
    public function make_validate_info_file($info) {
        // Assume no errors to start.
        $errors = FALSE;

        if (empty($info['core'])) {
            $this->make_error('BUILD_ERROR', dt("The 'core' attribute is required"));
            $errors = TRUE;
        }
        // Standardize on core.
        elseif (preg_match('/^(\d+)(\.(x|(\d+)(-[a-z0-9]+)?))?$/', $info['core'], $matches)) {
            // An exact version of core has been specified, so pass that to an
            // internal variable for storage.
            if (isset($matches[4])) {
                $info['core_release'] = $info['core'];
            }
            // Format the core attribute consistently.
            $info['core'] = $matches[1] . '.x';
        }
        else {
            $this->make_error('BUILD_ERROR', dt("The 'core' attribute !core has an incorrect format.", array('!core' => $info['core'])));
            $errors = TRUE;
        }

        if (!isset($info['api'])) {
            $info['api'] = self::MAKE_API;
            drush_log(dt("You need to specify an API version of two in your makefile:\napi = !api", array("!api" => MAKE_API)), LogLevel::WARNING);
        }
        elseif ($info['api'] != self::MAKE_API) {
            $this->make_error('BUILD_ERROR', dt("The specified API attribute is incompatible with this version of Drush Make."));
            $errors = TRUE;
        }

        $names = array();

        // Process projects.
        if (isset($info['projects'])) {
            if (!is_array($info['projects'])) {
                $this->make_error('BUILD_ERROR', dt("'projects' attribute must be an array."));
                $errors = TRUE;
            }
            else {
                // Filter out entries that have been forcibly removed via [foo] = FALSE.
                $info['projects'] = array_filter($info['projects']);

                foreach ($info['projects'] as $project => $project_data) {
                    // Project has an attributes array.
                    if (is_string($project) && is_array($project_data)) {
                        if (in_array($project, $names)) {
                            $this->make_error('BUILD_ERROR', dt("Project !project defined twice (remove the first projects[] = !project).", array('!project' => $project)));
                            $errors = TRUE;
                        }
                        $names[] = $project;
                        foreach ($project_data as $attribute => $value) {
                            // Prevent malicious attempts to access other areas of the
                            // filesystem.
                            if (in_array($attribute, array('subdir', 'directory_name', 'contrib_destination')) && !$this->make_safe_path($value)) {
                                $args = array(
                                  '!path' => $value,
                                  '!attribute' => $attribute,
                                  '!project' => $project,
                                );
                                $this->make_error('BUILD_ERROR', dt("Illegal path !path for '!attribute' attribute in project !project.", $args));
                                $errors = TRUE;
                            }
                        }
                    }
                    // Cover if there is no project info, it's just a project name.
                    elseif (is_numeric($project) && is_string($project_data)) {
                        if (in_array($project_data, $names)) {
                            $this->make_error('BUILD_ERROR', dt("Project !project defined twice (remove the first projects[] = !project).", array('!project' => $project_data)));
                            $errors = TRUE;
                        }
                        $names[] = $project_data;
                        unset($info['projects'][$project]);
                        $info['projects'][$project_data] = array();
                    }
                    // Convert shorthand project version style to array format.
                    elseif (is_string($project_data)) {
                        if (in_array($project, $names)) {
                            $this->make_error('BUILD_ERROR', dt("Project !project defined twice (remove the first projects[] = !project).", array('!project' => $project)));
                            $errors = TRUE;
                        }
                        $names[] = $project;
                        $info['projects'][$project] = array('version' => $project_data);
                    }
                    else {
                        $this->make_error('BUILD_ERROR', dt('Project !project incorrectly specified.', array('!project' => $project)));
                        $errors = TRUE;
                    }
                }
            }
        }
        if (isset($info['libraries'])) {
            if (!is_array($info['libraries'])) {
                make_error('BUILD_ERROR', dt("'libraries' attribute must be an array."));
                $errors = TRUE;
            }
            else {
                // Filter out entries that have been forcibly removed via [foo] = FALSE.
                $info['libraries'] = array_filter($info['libraries']);

                foreach ($info['libraries'] as $library => $library_data) {
                    if (is_array($library_data)) {
                        foreach ($library_data as $attribute => $value) {
                            // Unset disallowed attributes.
                            if (in_array($attribute, array('contrib_destination'))) {
                                unset($info['libraries'][$library][$attribute]);
                            }
                            // Prevent malicious attempts to access other areas of the
                            // filesystem.
                            elseif (in_array($attribute, array('contrib_destination', 'directory_name')) && !$this->make_safe_path($value)) {
                                $args = array(
                                  '!path' => $value,
                                  '!attribute' => $attribute,
                                  '!library' => $library,
                                );
                                $this->make_error('BUILD_ERROR', dt("Illegal path !path for '!attribute' attribute in library !library.", $args));
                                $errors = TRUE;
                            }
                        }
                    }
                }
            }
        }

        // Convert shorthand project/library download style to array format.
        foreach (array('projects', 'libraries') as $type) {
            if (isset($info[$type]) && is_array($info[$type])) {
                foreach ($info[$type] as $name => $item) {
                    if (!empty($item['download']) && is_string($item['download'])) {
                        $info[$type][$name]['download'] = array('url' => $item['download']);
                    }
                }
            }
        }

        // Apply defaults after projects[] array has been expanded, but prior to
        // external validation.
        $this->make_apply_defaults($info);

        foreach (drush_command_implements('make_validate_info') as $module) {
            $function = $module . '_make_validate_info';
            $return = $function($info);
            if ($return) {
                $info = $return;
            }
            else {
                $errors = TRUE;
            }
        }

        if ($errors) {
            return FALSE;
        }
        return $info;
    }

    /**
     * Verify the syntax of the given URL.
     *
     * Copied verbatim from includes/common.inc
     *
     * @see valid_url
     */
    function make_valid_url($url, $absolute = FALSE) {
        if ($absolute) {
            return (bool) preg_match("
      /^                                                      # Start at the beginning of the text
      (?:ftp|https?):\/\/                                     # Look for ftp, http, or https schemes
      (?:                                                     # Userinfo (optional) which is typically
        (?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*      # a username or a username and password
        (?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@          # combination
      )?
      (?:
        (?:[a-z0-9\-\.]|%[0-9a-f]{2})+                        # A domain name or a IPv4 address
        |(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\])         # or a well formed IPv6 address
      )
      (?::[0-9]+)?                                            # Server port number (optional)
      (?:[\/|\?]
        (?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})   # The path and query (optional)
      *)?
    $/xi", $url);
        }
        else {
            return (bool) preg_match("/^(?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})+$/i", $url);
        }
    }

    /**
     * Find, and possibly create, a temporary directory.
     *
     * @param boolean $set
     *   Must be TRUE to create a directory.
     * @param string $directory
     *   Pass in a directory to use. This is required if using any
     *   concurrent operations.
     *
     * @todo Merge with drush_tempdir().
     */
    function make_tmp($set = TRUE, $directory = NULL) {
        static $tmp_dir;

        if (isset($directory) && !isset($tmp_dir)) {
            $tmp_dir = $directory;
        }

        if (!isset($tmp_dir) && $set) {
            $tmp_dir = drush_find_tmp();
            if (strrpos($tmp_dir, '/') == strlen($tmp_dir) - 1) {
                $tmp_dir .= 'make_tmp_' . time() . '_' . uniqid();
            }
            else {
                $tmp_dir .= '/make_tmp_' . time() . '_' . uniqid();
            }
            if (!drush_get_option('no-clean', FALSE)) {
                drush_register_file_for_deletion($tmp_dir);
            }
            if (file_exists($tmp_dir)) {
                return $this->make_tmp(TRUE);
            }
            // Create the directory.
            drush_mkdir($tmp_dir);
        }
        return $tmp_dir;
    }

    /**
     * Removes the temporary build directory. On failed builds, this is handled by
     * drush_register_file_for_deletion().
     */
    function make_clean_tmp() {
        if (!($tmp_dir = $this->make_tmp(FALSE))) {
            return;
        }
        if (!drush_get_option('no-clean', FALSE)) {
            drush_delete_dir($tmp_dir);
        }
        else {
            drush_log(dt('Temporary directory: !dir', array('!dir' => $tmp_dir)), LogLevel::OK);
        }
    }

    /**
     * Prepare a Drupal installation, copying default.settings.php to settings.php.
     */
    function make_prepare_install($build_path) {
        $default = make_tmp() . '/__build__/sites/default';
        drush_copy_dir($default . DIRECTORY_SEPARATOR . 'default.settings.php', $default . DIRECTORY_SEPARATOR . 'settings.php', FILE_EXISTS_OVERWRITE);
        drush_mkdir($default . '/files');
        chmod($default . DIRECTORY_SEPARATOR . 'settings.php', 0666);
        chmod($default . DIRECTORY_SEPARATOR . 'files', 0777);
    }

    /**
     * Calculate a cksum on each file in the build, and md5 the resulting hashes.
     */
    function make_md5() {
        return drush_dir_md5(make_tmp());
    }

    /**
     * @todo drush_archive_dump() also makes a tar. Consolidate?
     */
    function make_tar($build_path) {
        $tmp_path = $this->make_tmp();

        drush_mkdir(dirname($build_path));
        $filename = basename($build_path);
        $dirname = basename($build_path, '.tar.gz');
        // Move the build directory to a more human-friendly name, so that tar will
        // use it instead.
        drush_move_dir($tmp_path . DIRECTORY_SEPARATOR . '__build__', $tmp_path . DIRECTORY_SEPARATOR . $dirname, TRUE);
        // Only move the tar file to it's final location if it's been built
        // successfully.
        if (drush_shell_exec("%s -C %s -Pczf %s %s", drush_get_tar_executable(), $tmp_path, $tmp_path . '/' . $filename, $dirname)) {
            drush_move_dir($tmp_path . DIRECTORY_SEPARATOR . $filename, $build_path, TRUE);
        };
        // Move the build directory back to it's original location for consistency.
        drush_move_dir($tmp_path . DIRECTORY_SEPARATOR . $dirname, $tmp_path . DIRECTORY_SEPARATOR . '__build__');
    }

    /**
     * Logs an error unless the --force-complete command line option is specified.
     */
    function make_error($error_code, $message) {
        if (drush_get_option('force-complete')) {
            drush_log("$error_code: $message -- build forced", LogLevel::WARNING);
        }
        else {
            return drush_set_error($error_code, $message);
        }
    }

    /**
     * Checks an attribute's path to ensure it's not maliciously crafted.
     *
     * @param string $path
     *   The path to check.
     */
    function make_safe_path($path) {
        return !preg_match("+^/|^\.\.|/\.\./+", $path);
    }
    /**
     * Get data based on the source.
     *
     * This is a helper function to abstract the retrieval of data, so that it can
     * come from files, STDIN, etc.  Currently supports filepath and STDIN.
     *
     * @param string $data_source
     *   The path to a file, or '-' for STDIN.
     *
     * @return string
     *   The raw data as a string.
     */
    function make_get_data($data_source) {
        if ($data_source == '-') {
            // See http://drupal.org/node/499758 before changing this.
            $stdin = fopen('php://stdin', 'r');
            $data = '';
            $has_input = FALSE;

            while ($line = fgets($stdin)) {
                $has_input = TRUE;
                $data .= $line;
            }

            if ($has_input) {
                return $data;
            }
            return FALSE;
        }
        // Local file.
        elseif (!strpos($data_source, '://')) {
            $data = file_get_contents($data_source);
        }
        // Remote file.
        else {
            $file = _make_download_file($data_source);
            $data = file_get_contents($file);
            drush_op('unlink', $file);
        }
        return $data;
    }

    /**
     * Apply any defaults.
     *
     * @param array &$info
     *   A parsed make array.
     */
    function make_apply_defaults(&$info) {
        if (isset($info['defaults'])) {
            $defaults = $info['defaults'];

            foreach ($defaults as $type => $default_data) {
                if (isset($info[$type])) {
                    foreach ($info[$type] as $project => $data) {
                        $info[$type][$project] = _drush_array_overlay_recursive($default_data, $info[$type][$project]);
                    }
                }
                else {
                    drush_log(dt("Unknown attribute '@type' in defaults array", array('@type' => $type)), LogLevel::WARNING);
                }
            }
        }
    }

    /**
     * Check if makefile overrides are allowed
     *
     * @param array $option
     *   The option to check.
     */
    function _make_is_override_allowed ($option) {
        $allow_override = drush_get_option('allow-override', 'all');

        if ($allow_override == 'all') {
            $allow_override = array();
        }
        elseif (!is_array($allow_override)) {
            $allow_override = _convert_csv_to_array($allow_override);
        }

        if ((empty($allow_override)) || ((in_array($option, $allow_override)) && (!in_array('none', $allow_override)))) {
            return TRUE;
        }
        drush_log(dt("'!option' not allowed; use --allow-override=!option or --allow-override=all to permit", array("!option" => $option)), LogLevel::WARNING);
        return FALSE;
    }

    /**
     * Gather any working copy options.
     *
     * @param array $download
     *   The download array.
     */
    function _get_working_copy_option($download) {
        $wc = '';

        if (_make_is_override_allowed('working-copy') && isset ($download['working-copy'])) {
            $wc = $download['working-copy'];
        }
        else {
            $wc = drush_get_option('working-copy');
        }
        return $wc;
    }

    /**
     * Given data from stdin, determine format.
     *
     * @return array|bool
     *   Returns parsed data if it matches any known format.
     */
    function _make_determine_format($data) {
        // Most .make files will have a `core` attribute. Use this to determine
        // the format.
        if (preg_match('/^\s*core:/m', $data)) {
            $parsed = ParserYaml::parse($data);
            $parsed['format'] = 'yaml';
            return $parsed;
        }
        elseif (preg_match('/^\s*core\s*=/m', $data)) {
            $parsed = ParserIni::parse($data);
            $parsed['format'] = 'ini';
            return $parsed;
        }

        // If the .make file did not have a core attribute, it is being included
        // by another .make file. Test YAML first to avoid segmentation faults from
        // preg_match in INI parser.
        $yaml_parse_exception = FALSE;
        try {
            if ($parsed = ParserYaml::parse($data)) {
                $parsed['format'] = 'yaml';
                return $parsed;
            }
        }
        catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
            // Note that an exception was thrown, and display after .ini parsing.
            $yaml_parse_exception = $e;
        }

        // Try INI format.
        if ($parsed = ParserIni::parse($data)) {
            $parsed['format'] = 'ini';
            return $parsed;
        }

        if ($yaml_parse_exception) {
            throw $e;
        }

        return drush_set_error('MAKE_STDIN_ERROR', dt('Unknown make file format'));
    }

}