<?php
// $Id$

/**
 * The base project class.
 */
class DrushMakeProject {
  /**
   * Set attributes and retrieve project information.
   */
  function __construct($project) {
    foreach ($project as $key => $value) {
      $this->{$key} = $value;
    }
    $this->downloadClass = DrushMakeDownload::factory($this);
    if (!$this->downloadClass) {
      drush_set_error(dt('Could not find download type @type', array('@type' => $this->download['type'])));
    }
  }

  /**
   * Build a project.
   */
  function make() {
    if (empty($this->made)) {
      $directory = $this->queue->addItem(array($this->downloadClass, 'download'));
      $full_path = $this->queue->addItem(array($this, 'makePath'), array(), $directory);
      $move = $this->queue->addItem(array($this, 'move'), array(), array($directory, $full_path));
      $this->queue->addItem(array($this, 'applyPatches'), array(), array($move));
      $this->queue->addItem(array($this, 'recurse'), array(), array($move, $directory));
    }
    return FALSE;
  }

  function makePath($directory) {
    $this->path = $this->generatePath();
    $this->fillPath($this->path);
    if (is_dir($this->path . '/' . $directory)) {
      drush_set_error(dt('Directory not empty: %directory', array('%directory' => $directory)));
      drush_shell_exec('rm -r ' . $directory);
    }
    return TRUE;
  }

  /**
   * Retrieve and apply any patches specified by the makefile to this project.
   */
  function applyPatches($project_directory) {
    if (!empty($this->patch)) {
      foreach ($this->patch as $url) {
        // Download the patch.
        list($filename) = array_reverse(explode('/', $url));
        if (!drush_shell_exec("wget {$url}")) {
          drush_shell_exec("curl -O {$url}");
        }
        if (file_exists($filename)) {
          drush_shell_exec("mv {$filename} {$project_directory}");
          drush_shell_exec("patch -p0 -d {$project_directory} < {$project_directory}/{$filename}");
          drush_print("{$this->name} patched with {$filename}.");
        }
        else {
          drush_set_error("Unable to download $filename from $url.");
        }
      }
    }
  }

  /**
   * Move a project to its final resting place.
   */
  function move($project_directory) {
    drush_shell_exec("mv {$project_directory} {$this->path}");
    if (!empty($this->directory_name)) {
      drush_shell_exec("mv {$this->path}/{$project_directory} {$this->path}/{$this->directory_name}");
      $project_directory = $this->directory_name;
    }
    return "{$this->path}/{$project_directory}";
  }

  /**
   * Generate the proper path for this project type.
   *
   * @param $base_path
   *   Whether to include the base_path in the generated patch. Defaults to
   *   true.
   */
  protected function generatePath($base = TRUE) {
    $path = array();
    if ($base) {
      $path[] = $this->tmp_path;
      $path[] = $this->base_path;
    }
    if (!empty($this->install_path)) {
      $path[] = $this->install_path;
    }
    if (!empty($this->subdir)) {
      $path[] = $this->subdir;
    }
    return implode('/', $path);
  }

  protected function fillPath($path) {
    $built_path = array();
    foreach (explode('/', $path) as $part) {
      if ($part == '.') {
        continue;
      }
      $built_path[] = $part;
      $current_path = implode('/', $built_path);
      if ($current_path && !is_dir($current_path)) {
        drush_op('mkdir', $current_path);
      }
    }
  }

  function recurse($path, $directory) {
    $makefiles = drush_scan_directory($path, '.make');
    if (!empty($makefiles)) {
      $build_path = $this->generatePath(FALSE) . '/' . $directory;
      foreach ($makefiles as $file) {
        $info  = $this->queue->addItem('drush_make_parse_info_file', $file->filename);
        $valid = $this->queue->addItem('drush_make_validate_info_file', array(), $info);
        $this->queue->addItem('drush_make_add_projects', array(trim($build_path, '/'), $this->tmp_path, $this->base_path), array($valid));
      }
    }
  }
}

class DrushMakeDownload {
  var $project;
  var $path;

  function __construct($project) {
    $this->project = $project;
    $this->path = !empty($this->project->directory_name) ? $this->project->directory_name : $this->project->name;
    $this->preDownload();
  }

  function factory($project) {
    include_once 'drush_make.download.' . $project->download['type'] . '.inc';
    $class = 'DrushMakeDownload_' . $project->download['type'];
    if (class_exists($class)) {
      return new $class($project);
    }
    else {
      return FALSE;
    }
  }

  function preDownload() {
    return FALSE;
  }

  function download() {
    return FALSE;
  }
}

class DrushMakeQueue {
  function __construct() {
    $this->items = array();
    $this->id = 0;
    $this->returns = array();
    $this->current = -1;
  }

  function addItem($function, $parameters = array(), $dependencies = NULL, $position = 'end') {
    if (isset($dependencies) && !is_array($dependencies)) {
      $dependencies = array($dependencies);
    }
    if (isset($parameters) && !is_array($parameters)) {
      $parameters = array($parameters);
    }
    $id = $this->generateId();
    if (isset($position)) {
      $array_function = ($position == 'end' ? 'array_push' : 'array_unshift');
    }
    else {
      $array_function = ($this->current != -1 ? 'array_unshift' : 'array_push');
    }
    $array_function($this->items, array(
      'function' => $function,
      'parameters' => $parameters,
      'dependencies' => $dependencies,
      'id' => $id,
    ));
    return $id;
  }

  function execute() {
    while (count($this->items)) {
      $item = array_shift($this->items);
      $this->current = $item['id'];
      $result = TRUE;
      if (!empty($item['dependencies'])) {
        foreach ($item['dependencies'] as $id) {
          if (!isset($this->returns[$id]) || $this->returns[$id] === FALSE) {
            $this->returns[$item['id']] = FALSE;
            continue 2;
          }
          else {
            $item['parameters'][] = $this->returns[$id];
          }
        }
      }
      $item['parameters'][] = $this;
      $this->returns[$item['id']] = call_user_func_array($item['function'], $item['parameters']);
      $this->current = -1;
    }
    return TRUE;
  }

  protected function generateId() {
    // Potentially could use some more complex logic.
    return $this->id++;
  }

  function getId() {
    return $this->current;
  }

  function getReturn($index) {
    return $this->returns[$index];
  }
}

