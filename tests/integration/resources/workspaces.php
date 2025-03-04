<?php

$workspace = \Drupal\workspaces\Entity\Workspace::load('stage');
Drupal::service('workspaces.manager')->setActiveWorkspace($workspace);
