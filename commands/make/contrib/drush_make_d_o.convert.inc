<?php
// $Id$

class DrushMakeDrupalorgConverter {
  function __construct($from, $to) {
    $this->from = $from;
    $this->to = $to;
  }

  function run() {
    $this->read();
    $this->convert();
    $this->write();
  }

  function read() {
    $this->old_info = drush_make_parse_info_file($this->from);
  }

  function convert() {
    // First order of business: convert core version.
    $core = explode('.', $this->old_info['core']);
    $this->core = $core[0] . '.x';
    if (!isset($core[1]) || $core[1] == 'x') {
      $this->new_info['core'] = $this->latestVersion('drupal');
      drush_log(dt('Setting the core version to @version.', array('@version' => $this->new_info['core'])), 'ok');
    }
    else {
      $this->new_info['core'] = $this->old_info['core'];
    }

    $attribute_whitelist = array('version', 'subdir');
    if (isset($this->old_info['projects']) && is_array($this->old_info['projects'])) {
      foreach ($this->old_info['projects'] as $key => $project) {
        if (is_numeric($key)) {
          $version = $this->latestVersion($project);
          if ($version) {
            drush_log(dt('Setting version for @project to @version', array('@project' => $project, '@version' => $version)), 'ok');
            $this->new_info['projects'][$project] = $version;
          }
        }
        elseif (!is_array($project)) {
          $this->new_info['projects'][$key] = $project;
        }
        else {
          if (isset($project['download'])) {
            drush_log(dt('Custom downloads aren\'t allowed for project @project', array('@project' => $key)), 'ok');
            continue;
          }
          elseif (isset($project['location'])) {
            drush_log(dt('Custom locations aren\'t allowed for project @project', array('@project' => $key)), 'ok');
            continue;
          }
          foreach ($project as $k => $v) {
            if (!in_array($k, $attribute_whitelist)) {
              drush_log(dt('Attribute @attribute not allowed for @project', array('@attribute' => $k, '@project' => $key)));
              unset($project[$k]);
            }
          }
          if (!isset($project['version'])) {
            $version = $this->latestVersion($key);
            if ($version) {
              drush_log(dt('Setting version for @project to @version', array('@project' => $key, '@version' => $version)), 'ok');
              $project['version'] = $version;
            }
            else {
              continue;
            }
            $this->new_info['projects'][$key] = $project;
          }
        }
      }
    }

    if (isset($this->new_info['projects']['drupal'])) {
      unset($this->new_info['projects']['drupal']);
      drush_log(dt('Removed Drupal core'), 'ok');
    }

    if (isset($this->old_info['libraries'])) {
      drush_log(dt('Libraries removed.'), 'ok');
    }
  }

  function write() {
    $output = $this->render($this->new_info);
    file_put_contents($this->to, $output);
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
        $output .= $key_definition . ' = ' . var_export($value, TRUE) . "\n";
      }
      if (count($parents) < 2) {
        $output .= "\n";
      }
    }
    return $output;
  }

  function latestVersion($project, $branch = NULL) {
    $project = array(
      'name'           => $project,
      'core'           => $this->core,
      'version'        => ($branch ? $branch : DRUSH_MAKE_VERSION_BEST),
      'location'       => DRUSH_MAKE_UPDATE_DEFAULT_URL,
    );
    $project = drush_make_updatexml($project);
    return $project['version'];
  }
}

