<?php

use Drupal\Component\Uuid\Uuid;

/**
 * Implementation of hook_drush_help().
 */
function uuid_drush_help($section) {
  switch ($section) {
    case 'meta:uuid:title':
      return dt('UUID commands');
    case 'meta:UUID:summary':
      return dt('Generates Universally Unique IDentifiers.');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function uuid_drush_command() {
  $items['generate-uuid'] = array(
    'description' => 'Generate a UUID.',
    'examples' => array(
      "drush generate-uuid" => "Outputs a Universally Unique IDentifier.",
    ),
    'aliases' => array('uuid'),
  );

  return $items;
}

/**
 * Generates and prints a new UUID.
 */
function drush_uuid_generate_uuid() {
  $uuid = new Uuid();
  drush_print($uuid->generate());
  return TRUE;
}
