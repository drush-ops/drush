<?php
// $Id$

class DrushMakeQueue {
  var $terminated = FALSE;

  function __construct() {
    $this->items = array();
    $this->id = 0;
    $this->returns = array(0 => FALSE);
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
    // Allow altering.
    $implementations = drush_command_implements('drush_make_command');
    foreach ($implementations as $implementation) {
      $alter = $implementation . '_drush_make_command';
      $result = $alter($function, $parameters, $dependencies, $id, $queue);
      if ($result === FALSE) {
        return 0;
      }
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
    if ($this->terminated) {
      $this->terminated = FALSE;
      return FALSE;
    }
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

  function terminate() {
    $this->items = array();
    $this->terminated = TRUE;
  }
}

