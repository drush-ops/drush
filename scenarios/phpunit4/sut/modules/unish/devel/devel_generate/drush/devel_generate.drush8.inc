<?php

/**
 * @file
 *  Integration with Drush8. Drush9 commands are in src/Commands.
 */
use Drupal\devel_generate\DevelGenerateBaseInterface;
use Drupal\devel_generate\DevelGeneratePluginManager;

/**
 * Implementation of hook_drush_command().
 */
function devel_generate_drush_command() {
  $items['generate-users'] = array(
    'callback' => 'drush_devel_generate',
    'callback arguments' => array(
      'plugin_id' => 'user',
    ),
    'description' => 'Create users.',
    'arguments' => array(
      'num' => 'Number of users to generate.',
    ),
    'options' => array(
      'kill' => 'Delete all users before generating new ones.',
      'roles' => 'A comma delimited list of role IDs which should be granted to the new users. No need to specify authenticated user role.',
      'pass' => 'Specify a password to be set for all generated users.',
    ),
    'aliases' => array('genu'),
  );
  $items['generate-terms'] = array(
    'callback' => 'drush_devel_generate',
    'callback arguments' => array(
      'plugin_id' => 'term',
    ),
    'description' => 'Create terms in specified vocabulary.',
    'arguments' => array(
      'machine_name' => 'Vocabulary machine name into which new terms will be inserted.',
      'num' => 'Number of terms to insert. Defaults to 10.',
    ),
    'options' => array(
      'kill' => 'Delete all terms in specified vocabulary before generating.',
      'feedback' => 'An integer representing interval for insertion rate logging. Defaults to 1000',
      'pipe' => 'Returns the list of generated terms, one per line.',
    ),
    'aliases' => array('gent'),

  );
  $items['generate-vocabs'] = array(
    'callback' => 'drush_devel_generate',
    'callback arguments' => array(
      'plugin_id' => 'vocabulary',
    ),
    'description' => 'Create vocabularies.',
    'arguments' => array(
      'num' => 'Number of vocabularies to create. Defaults to 1.',
    ),
    'options' => array(
      'kill' => 'Delete all vocabularies before generating.',
      'pipe' => 'Returns the list of generated vocabularies, one per line.',
    ),
    'aliases' => array('genv'),
  );
  $items['generate-content'] = array(
    'callback' => 'drush_devel_generate',
    'callback arguments' => array(
      'plugin_id' => 'content',
    ),
    'description' => 'Create content.',
    'drupal dependencies' => array('devel_generate'),
    'arguments' => array(
      'num' => 'Number of nodes to generate.',
      'max_comments' => 'Maximum number of comments to generate.',
    ),
    'options' => array(
      'kill' => 'Delete all content before generating new content.',
      'types' => 'A comma delimited list of content types to create. Defaults to page,article.',
      'feedback' => 'An integer representing interval for insertion rate logging. Defaults to 1000',
      'skip-fields' => 'A comma delimited list of fields to omit when generating random values',
      'languages' => 'A comma-separated list of language codes',
    ),
    'aliases' => array('genc'),
  );
  $items['generate-menus'] = array(
    'callback' => 'drush_devel_generate',
    'callback arguments' => array(
      'plugin_id' => 'menu',
    ),
    'description' => 'Create menus and menu items.',
    'drupal dependencies' => array('devel_generate'), // Remove these once devel.module is moved down a directory. http://drupal.org/node/925246
    'arguments' => array(
      'number_menus' => 'Number of menus to generate. Defaults to 2.',
      'number_links' => 'Number of links to generate. Defaults to 50.',
      'max_depth' => 'Max link depth. Defaults to 3',
      'max_width' => 'Max width of first level of links. Defaults to 8.',
    ),
    'options' => array(
      'kill' => 'Delete all previously generated menus and links before generating new menus and links.',
      'pipe' => 'Returns the list of generated menus, one per line.',
    ),
    'aliases' => array('genm'),
  );
  return $items;
}

/**
 * Implements drush_hook_COMMAND_validate().
 */
function drush_devel_generate_generate_users_validate() {

  //Array of "Callback arguments" and "command line args".
  $params = func_get_args();
  _drush_plugin_validate($params);
}

/**
 * Implements drush_hook_COMMAND_validate().
 */
function drush_devel_generate_generate_terms_validate() {

  //Array of "Callback arguments" and "command line args".
  $params = func_get_args();
  _drush_plugin_validate($params);
}

/**
 * Implements drush_hook_COMMAND_validate().
 */
function drush_devel_generate_generate_vocabs_validate() {

  //Array of "Callback arguments" and "command line args".
  $params = func_get_args();
  _drush_plugin_validate($params);
}

/**
 * Implements drush_hook_COMMAND_validate().
 */
function drush_devel_generate_generate_content_validate() {

  //Array of "Callback arguments" and "command line args".
  $params = func_get_args();
  _drush_plugin_validate($params);
}

/**
 * Implements drush_hook_COMMAND_validate().
 */
function drush_devel_generate_generate_menus_validate() {

  //Array of "Callback arguments" and "command line args".
  $params = func_get_args();
  _drush_plugin_validate($params);
}

/**
 * Helper function which returns an array with a plugin instance
 * for a given id and the validated values ready to be used by
 * the generate() function of the plugin.
 */
function _drush_plugin_validate($params) {

  $instance_and_values = &drupal_static('drush_devel_generate_generate_validate');
  //Getting plugin_id and leaving the command line args
  $plugin_id = array_shift($params);

  if (!isset($instance_and_values[$plugin_id])) {

    /** @var DevelGeneratePluginManager $manager */
    $manager = \Drupal::service('plugin.manager.develgenerate');

    /** @var DevelGenerateBaseInterface $instance */
    $instance = $manager->createInstance($plugin_id, array());

    //Plugin instance suit params in order to fit for generateElements
    $values = $instance->validateDrushParams($params);
    $instance_and_values[$plugin_id]['instance'] = $instance;
    $instance_and_values[$plugin_id]['values'] = $values;
  }

  return $instance_and_values[$plugin_id];
}

/**
 * Command callback. Generate a number of elements.
 */
function drush_devel_generate() {
  $params = func_get_args();
  $plugin_id = array_shift($params);
  $instance_and_values = drupal_static('drush_devel_generate_generate_validate');

  /** @var DevelGenerateBaseInterface $instance */
  $instance = $instance_and_values[$plugin_id]['instance'];
  $values = $instance_and_values[$plugin_id]['values'];
  $instance->generate($values);

}
