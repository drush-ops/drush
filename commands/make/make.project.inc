<?php

/**
 * The base project class.
 */
class DrushMakeProject {

  /**
   * TRUE if make() has been called, otherwise FALSE.
   */
  protected $made = FALSE;

  /**
   * Keep track of instances.
   *
   * @see DrushMakeProject::getInstance()
   */
  protected static $self = array();

  /**
   * Set attributes and retrieve project information.
   */
  protected function __construct($project) {
    $project['base_contrib_destination'] = $project['contrib_destination'];
    foreach ($project as $key => $value) {
      $this->{$key} = $value;
    }
  }

  /**
   * Get an instance for the type and project.
   *
   * @param $type
   *   Type of project: core, library, module, profile, or translation.
   * @param $project
   *   Project information.
   * @return
   *   An instance for the project or FALSE if invalid type.
   */
  public static function getInstance($type, $project) {
    if (!isset(self::$self[$type][$project['name']])) {
      $class = 'DrushMakeProject_' . $type;
      self::$self[$type][$project['name']] = class_exists($class) ? new $class($project) : FALSE;
    }
    return self::$self[$type][$project['name']];
  }

  /**
   * Build a project.
   */
  function make() {
    if ($this->made) {
      drush_log(dt('Attempt to build project @project more then once prevented.', array('@project' => $this->name)));
      return TRUE;
    }
    $this->made = TRUE;

    $download_location = $this->findDownloadLocation();
    if (make_download_factory($this->name, $this->download, $download_location) === FALSE) {
      return FALSE;
    }
    if (!$this->addLockfile($download_location)) {
      return FALSE;
    }
    if (!$this->applyPatches($download_location)) {
      return FALSE;
    }
    if (!$this->getTranslations($download_location)) {
      return FALSE;
    }
    if (!$this->recurse($download_location)) {
      return FALSE;
    }
    return TRUE;
  }

  function findDownloadLocation() {
    $this->path = $this->generatePath();
    $this->project_directory = !empty($this->directory_name) ? $this->directory_name : $this->name;
    $this->download_location = $this->path . '/' . $this->project_directory;
    // This directory shouldn't exist yet -- if it does, stop,
    // unless overwrite has been set to TRUE.
    if (is_dir($this->download_location) && !$this->overwrite) {
      drush_set_error(dt('Directory not empty: %directory', array('%directory' => $this->download_location)));
      return FALSE;
    }
    elseif ($this->download['type'] === 'pm') {
      // pm-download will create the final contrib directory.
      drush_mkdir(dirname($this->download_location));
    }
    else {
      drush_mkdir($this->download_location);
    }
    return $this->download_location;
  }

  /**
   * Retrieve and apply any patches specified by the makefile to this project.
   */
  function applyPatches($project_directory) {
    if (empty($this->patch)) {
      return TRUE;
    }

    $patches_txt = '';
    $ignore_checksums = drush_get_option('ignore-checksums');
    foreach ($this->patch as $info) {
      if (!is_array($info)) {
        $info = array('url' => $info);
      }
      // Download the patch.
      if ($filename = _make_download_file($info)) {
        $patched = FALSE;
        $output = '';
        // Test each patch style; -p1 is the default with git. See
        // http://drupal.org/node/1054616
        $patch_levels = array('-p1', '-p0');
        foreach ($patch_levels as $patch_level) {
          $checked = drush_shell_exec('cd %s && GIT_WORK_TREE=. git apply --check %s %s --verbose', $project_directory, $patch_level, $filename);
          if ($checked) {
            // Apply the first successful style.
            $patched = drush_shell_exec('cd %s && GIT_WORK_TREE=. git apply %s %s --verbose', $project_directory, $patch_level, $filename);
            break;
          }
        }

        // In some rare cases, git will fail to apply a patch, fallback to using
        // the 'patch -p0' command.
        if (!$patched) {
          $patched = drush_shell_exec("patch -p0 -d %s < %s", $project_directory, $filename);
        }

        if ($output = drush_shell_exec_output()) {
          // Log any command output, visible only in --verbose or --debug mode.
          drush_log(implode("\n", $output));
        }

        // Set up string placeholders to pass to dt().
        $dt_args = array(
          '@name' => $this->name,
          '@filename' => basename($filename),
        );

        if ($patched) {
          if (!$ignore_checksums && !_make_verify_checksums($info, $filename)) {
             return FALSE;
          }
          $patches_txt .= '- ' . $info['url'] . "\n";
          drush_log(dt('@name patched with @filename.', $dt_args), 'ok');
        }
        else {
          make_error('PATCH_ERROR', dt("Unable to patch @name with @filename.", $dt_args));
        }
        drush_op('unlink', $filename);
      }
      else {
        make_error('DOWNLOAD_ERROR', 'Unable to download ' . $info['url'] . '.');
        return FALSE;
      }
    }
    if (!empty($patches_txt) && !drush_get_option('no-patch-txt') && !file_exists($project_directory . '/PATCHES.txt')) {
      $patches_txt = "The following patches have been applied to this project:\n" .
        $patches_txt .
        "\nThis file was automatically generated by Drush Make (http://drupal.org/project/make).";
      file_put_contents($project_directory . '/PATCHES.txt', $patches_txt);
      drush_log('Generated PATCHES.txt file for ' . $this->name, 'ok');
    }
    return TRUE;
  }

  /**
   * Add a lock file.
   */
  function addLockfile($project_directory) {
    if (!empty($this->lock)) {
      file_put_contents($project_directory . '/.drush-lock-update', $this->lock);
    }
    return TRUE;
  }

  /**
   * Retrieve translations for this project.
   */
  function getTranslations($project_directory) {
    static $cache = array();
    $langcodes = drush_get_option('translations', FALSE);
    if ($langcodes && $this->version !== drush_get_option('drush-make-version-best') && in_array($this->type, array('core', 'module', 'profile', 'theme'), TRUE)) {
      // Support the l10n_path, l10n_url keys from l10n_update. Note that the
      // l10n_server key is not supported.
      if (isset($this->l10n_path)) {
        $update_url = $this->l10n_path;
      }
      else {
        if (isset($this->l10n_url)) {
          $l10n_server = $this->l10n_url;
        }
        else {
          $l10n_server = FALSE;
        }
        if ($l10n_server) {
          if (!isset($cache[$l10n_server])) {
            if ($filename = _make_download_file($l10n_server)) {
              $server_info = simplexml_load_string(file_get_contents($filename));
              $cache[$l10n_server] = !empty($server_info->update_url) ? $server_info->update_url : FALSE;
              drush_op('unlink', $filename);
            }
          }
          if ($cache[$l10n_server]) {
            $update_url = $cache[$l10n_server];
          }
          else {
            make_error('XML_ERROR', dt("Could not retrieve l10n update url for %project.", array('%project' => $project['name'])));
            return FALSE;
          }
        }
      }
      if ($update_url) {
        $failed = array();
        $langcodes = explode(',', $langcodes);
        foreach ($langcodes as $langcode) {
          $variables = array(
            '%project' => $this->name,
            '%release' => $this->type === 'core' ? $this->version : "{$this->core}-{$this->version}",
            '%core' => $this->core,
            '%language' => $langcode,
            '%filename' => '%filename',
          );
          $url = strtr($update_url, $variables);

          // Download the translation file.
          if ($filename = _make_download_file($url)) {
            // If this is the core project type, download the translation file
            // and create two copies:
            // 1. To profiles/default/translations. It must be named
            //    langcode.po to be used properly by the installer.
            // 2. To modules/system/translations where it can be detected for
            //    import by other non-default install profiles.
            if ($this->type === 'core') {
              drush_mkdir($project_directory . '/profiles/default/translations');
              drush_mkdir($project_directory . '/modules/system/translations');
              drush_shell_exec("cp %s %s", $filename, $project_directory . '/profiles/default/translations/' . $langcode . '.po');
              drush_shell_exec("mv %s %s", $filename, $project_directory . '/modules/system/translations/' . $langcode . '.po');
            }
            else {
              drush_mkdir($project_directory . '/translations');
              drush_shell_exec("mv %s %s", $filename, $project_directory . '/translations/' . $langcode . '.po');
            }
          }
          else {
            $failed[] = $langcode;
          }
        }
        if (empty($failed)) {
          drush_log('All translations downloaded for ' . $this->name, 'ok');
        }
        else {
          drush_log('Unable to download translations for '. $this->name .': ' . implode(', ', $failed), 'warning');
        }
      }
    }
    return TRUE;
  }

  /**
   * Generate the proper path for this project type.
   *
   * @param $base
   *   Whether include the base part (tmp dir). Defaults to TRUE.
   */
  protected function generatePath($base = TRUE) {
    $path = array();
    if ($base) {
      $path[] = make_tmp();
      $path[] = '__build__';
    }
    if (!empty($this->contrib_destination)) {
      $path[] = $this->contrib_destination;
    }
    if (!empty($this->subdir)) {
      $path[] = $this->subdir;
    }
    return implode('/', $path);
  }

  /**
   * Return the proper path for dependencies to be placed in.
   *
   * @return
   *   The path that dependencies will be placed in.
   */
  protected function buildPath($directory) {
    return $this->base_contrib_destination;
  }

  function recurse($path) {
    $makefile = $this->download_location . '/' . $this->name . '.make';
    if (!file_exists($makefile)) {
      $makefile = $this->download_location . '/drupal-org.make';
      if (!file_exists($makefile)) {
        return TRUE;
      }
    }
    drush_log(dt("Found makefile: %makefile", array("%makefile" => basename($makefile))), 'ok');

    $info = make_parse_info_file($makefile);
    if (!($info = make_validate_info_file($info))) {
      return FALSE;
    }
    $build_path = $this->buildPath($this->name);
    make_projects(TRUE, trim($build_path, '/'), $info, $this->build_path);
    make_libraries(trim($build_path, '/'), $info, $this->build_path);

    return TRUE;
  }
}

class DrushMakeProject_Core extends DrushMakeProject {
  protected function __construct(&$project) {
    parent::__construct($project);
    $this->contrib_destination = '';
  }

  function findDownloadLocation() {
    $this->path = $this->download_location = $this->generatePath();
    $this->project_directory = '';
    if (is_dir($this->download_location)) {
      drush_set_error(dt('Directory not empty: %directory', array('%directory' => $this->download_location)));
      return FALSE;
    }
    elseif ($this->download['type'] === 'pm') {
      // pm-download will create the final __build__ directory, so nothing to do here.
    }
    else {
      drush_mkdir($this->download_location);
    }
    return $this->download_location;
  }
}

class DrushMakeProject_Library extends DrushMakeProject {
  protected function __construct(&$project) {
    parent::__construct($project);
    // Allow libraries to specify where they should live in the build path.
    if (isset($project['destination'])) {
      $project_path = $project['destination'];
    }
    else {
      $project_path = 'libraries';
    }

    $this->contrib_destination = ($this->base_contrib_destination != '.' ? $this->base_contrib_destination . '/' : '') . $project_path;
  }
  // No recursion for libraries, sorry :-(
  function recurse($path) {}
  // Neither translations.
  function getTranslations($download_location) {}
}

class DrushMakeProject_Module extends DrushMakeProject {
  protected function __construct(&$project) {
    parent::__construct($project);
    $this->contrib_destination = ($this->base_contrib_destination != '.' ? $this->base_contrib_destination . '/' : '') . 'modules';
  }
}

class DrushMakeProject_Profile extends DrushMakeProject {
  protected function __construct(&$project) {
    parent::__construct($project);
    $this->contrib_destination = (!empty($this->destination) ? $this->destination : 'profiles');
  }

  protected function buildPath($directory) {
    return $this->generatePath(FALSE) . '/' . $directory;
  }
}

class DrushMakeProject_Theme extends DrushMakeProject {
  protected function __construct(&$project) {
    parent::__construct($project);
    $this->contrib_destination = ($this->base_contrib_destination != '.' ? $this->base_contrib_destination . '/' : '') . 'themes';
  }
}

class DrushMakeProject_Translation extends DrushMakeProject {
  protected function __construct(&$project) {
    parent::__construct($project);
    switch($project['core']) {
      case '5.x':
        // Don't think there's an automatic place we can put 5.x translations,
        // so we'll toss them in a translations directory in the Drupal root.
        $this->contrib_destination = ($this->base_contrib_destination != '.' ? $this->base_contrib_destination . '/' : '') . 'translations';
        break;
      default:
        $this->contrib_destination = '';
        break;
    }
  }
}
