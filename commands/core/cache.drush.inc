<?php

use Drush\Log\LogLevel;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implementation of hook_drush_help().
 */
function cache_drush_help($section) {
  switch ($section) {
    case 'meta:cache:title':
      return dt('Cache commands');
    case 'meta:cache:summary':
      return dt('Interact with Drupal\'s cache API.');
  }
}



