<?php
// $Id$

class DrushMakeDrupalorgConverter {
  function __construct($from, $to = NULL) {
    $this->from = $from;
    $this->to = $to;
    $this->log = array();
  }

  function run() {
    if ($this->read()) {
      if ($this->convert()) {
        $this->write();
      }
    }
  }

  function read() {
    if (!($this->old_info = drush_make_parse_info_file($this->from))) {
      drush_make_error('FILE_ERROR', dt("Unable to parse file %file", array('%file' => $this->from)));
      return FALSE;
    }
    return TRUE;
  }

  function log($message, $type = 'ok') {
    // Save the log messages so they can be put into the output for the .make
    // file.
    if (empty($this->to)) {
      $this->log[] = "[$type]: \t $message";
    }
    // Only log to screen if we're outputting the conversion to file.
    else {
      drush_log($message, $type);
    }
  }

  function convert() {
    // Run the regular validation first.  This performs basic syntax
    // validation, and also pre-converts special syntax into a standard
    // format. Since the info file validation doesn't return any data if
    // validation fails, we have to bail here.
    if (!($this->old_info = drush_make_validate_info_file($this->old_info))) {
      return FALSE;
    }

    // The list of currently allowed top-leval attributes.
    $top_level_attribute_whitelist = array('core', 'core_release', 'projects');

    // The list of currently allowed project-level attributes.
    $project_attribute_whitelist = array('version', 'subdir');

    // The list of currently disallowed projects.
    $project_disallowed_list = array('drupal');

    // Assume no errors to start.
    $errors = FALSE;

    // First order of business: convert core version.
    $this->core = $this->old_info['core'];
    if (empty($this->old_info['core_release'])) {
      $parts = explode('.', $this->core);
      $core_major = $parts[0];
      // Check for a stable release on the branch.
      list($result, $final_version) = $this->latestVersion('drupal', $core_major);
      if ($result) {
        $this->new_info['core'] = $final_version;
        $this->log(dt('Setting the core version to %version.', array('%version' => $final_version)));
      }
      else {
        drush_make_error('BUILD_ERROR', dt("Drupal core does not have an official release for version '%version' yet.", array('%version' => $final_version)));
        $errors = TRUE;
      }
    }
    else {
      $this->new_info['core'] = $this->core;
    }

    if (isset($this->old_info['projects'])) {
      foreach ($this->old_info['projects'] as $project => $project_data) {

        // Skip conversion for disallowed projects.
        if (in_array($project, $project_disallowed_list)) {
          if ($project == 'drupal') {
            $this->log(dt("It is not permitted to include Drupal core as a project in an install profile -- it has been removed", 'warning'));
          }
          else {
            $this->log(dt("Removed disallowed project '%project'", array('%project' => $project)), 'warning');
          }
          continue;
        }
 
        // Clean out disallowed project attributes.
        foreach ($project_data as $attribute => $attribute_value) {
          if (!in_array($attribute, $project_attribute_whitelist)) {
            $this->log(dt("The '%attribute' attribute is not allowed for the '%project' project, removing.", array('%attribute' => $attribute, '%project' => $project)), 'warning');
            unset($project_data[$attribute]);
          }
        }

        // Reset the versioning variables to start fresh for every project.
        $version = NULL;
        $result = FALSE;
        $final_version = '';

        // Figure out the basic version string.
        if (empty($project_data['version'])) {
          $version = drush_get_option('drush-make-version-best');
        }
        elseif (preg_match('/^(\d+)((\.\d+)(-[a-z0-9]+)?)?$/', $project_data['version'], $matches)) {
          // Branch version only.
          if (empty($matches[2])) {
            $version = $matches[1];
          }
          // Development snapshots not allowed.
          elseif (!empty($matches[4]) && $matches[4] == '-dev') {
            drush_make_error('BUILD_ERROR', dt("An official release is required for '%project' -- a development version cannot be used.", array('%project' => $project)));
            $errors = TRUE;
            // Move on, nothing more needs to be done with this project.
            continue;
          }
          // All regular releases.
          else {
            $version = $matches[1] . $matches[2];
          }
        }

        // Use the update XML to verify this project actually has a valid 
        // release.
        if (isset($version)) {
          list($result, $final_version) = $this->latestVersion($project, $version);
        }
        if ($result) {
          $this->log(dt("Setting version for project '%project' to '%version'", array('%project' => $project, '%version' => $final_version)));
          $project_data['version'] = $final_version;
          if (array_keys($project_data) == array('version')) {
            $project_data = $project_data['version'];
          }

          $this->new_info['projects'][$project] = $project_data;
        }
        else {
          drush_make_error('BUILD_ERROR', dt("Project '%project' does not have an official release for version '%version' yet.", array('%project' => $project, '%version' => $final_version)));
          $errors = TRUE;
        }
      }
    }

    // Clean out disallowed top-level attributes.
    foreach ($this->old_info as $attribute => $attribute_value) {
      if (!in_array($attribute, $top_level_attribute_whitelist)) {
        $this->log(dt("The '%attribute' make file attribute is not allowed, removing.", array('%attribute' => $attribute, )), 'warning');
      }
    }

    if ($errors) {
      return FALSE;
    }
    return TRUE;
  }

  function write() {
    $output = $this->render($this->new_info);
    // Write to stdout.
    if (empty($this->to)) {
      $log = '';
      if (!empty($this->log)) {
        $log .= dt("; Logged conversion messages follow. The final converted .make file follows these messages.\n");
        foreach ($this->log as $message) {
          $log .= "; $message\n";
        }
      }
      print $log . $output;
    }
    // Write to file.
    else {
      if (file_put_contents($this->to, $output)) {
        drush_log(dt("Successfully wrote converted .make file %file.", array('%file' => $this->to)), 'ok');
      }
      else {
        drush_make_error('FILE_ERROR', dt("Unable to write .make file %file.", array('%file' => $this->to)));
      }
    }
  }

  function render($info, $parents = array()) {
    $output = '';
    foreach ($info as $key => $value) {
      if (is_numeric($key)) {
        $key = '';
      }
      if (is_array($value)) {
        $p = $parents;
        $p[] = $key;
        $output .= $this->render($value, $p);
      }
      else {
        // For simplicity
        $first = TRUE;
        $p = $parents;
        $p[] = $key;
        foreach ($p as $parent) {
          if ($first) {
            $key_definition = $parent;
            $first = FALSE;
          }
          else {
            $key_definition .= '[' . $parent . ']';
          }
        }
        $output .= "$key_definition = $value\n";
      }
      if (count($parents) < 2) {
        $output .= "\n";
      }
    }
    return $output;
  }

  function latestVersion($project, $version) {
    $project = array(
      'name'           => $project,
      'core'           => $this->core,
      'version'        => $version,
      'location'       => drush_get_option('drush-make-update-default-url'),
    );
    $project = drush_make_updatexml($project);

    // Check for bad projects and branch releases.
    // Because the version data we get back from the XML may not be the same
    // data we passed, and because we can fail even if version data is
    // returned, track both the success/failure of the request, and  the
    // version data.
    if (empty($project)) {
      $final_version = $version;
      $result = FALSE;
    }
    elseif (preg_match('/.*-dev$/', $project['version'])) {
      $final_version = $project['version'];
      $result = FALSE;
    }
    else {
      $final_version = $project['version'];
      $result = TRUE;
    }
    return array($result, $final_version);
  }
}

/**
 * Custom logging function for conversions going to STDOUT.
 *
 * Drush doesn't output drush_set_error() messages to STDERR, lamo...  :(
 *
 * This helper function overcomes that deficiency by manually writing error
 * messages to STDERR in the case where output is going to STDOUT.
 */
function drush_make_d_o_convert_log_stdout_handler($entry) {
  if ($entry['type'] == 'error' || $entry['type'] == 'failed') {
    fwrite(STDERR, dt("ERROR") . ": " .  $entry['message'] . "\n");
  }
}

