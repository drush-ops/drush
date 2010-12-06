<?php
// $Id$

/**
 * @file
 *   Documentation commands providing various topics.
 */

/**
 * Implementation of hook_drush_command().
 *
 * @return
 *   An associative array describing your command(s).
 */
function docs_drush_command() {
  //
  // Topic commands.
  // Any commandfile may add topics.
  // Set 'topic' => TRUE to indicate the command is a topic (REQUIRED)
  // Begin the topic name with the name of the commandfile (just like
  // any other command).
  //
  $items['docs-readme'] = array(
    'description' => dt('README.txt'),
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(DRUSH_BASE_PATH . '/README.txt'),
  );
  $items['docs-configuration'] = array(
    'description' => dt('Drush configuration overview with examples'),
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(DRUSH_BASE_PATH . '/examples/example.drushrc.php'),
  );
  $items['docs-aliases'] = array(
    'description' => dt('Site aliases overview with examples'),
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(DRUSH_BASE_PATH . '/examples/example.aliases.drushrc.php'),
  );
  $items['docs-bootstrap'] = array(
    'description' => dt('Information about the drush bootstrap process.'),
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(DRUSH_BASE_PATH . '/docs/bootstrap.txt'),
  );
  $items['docs-scripts'] = array(
    'description' => dt('Overview on how to write drush shell scripts.'),
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(DRUSH_BASE_PATH . '/docs/shellscripts.txt'),
  );
  $items['docs-commands'] = array(
    'description' => dt('Overview on how to write drush commands.'),
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(DRUSH_BASE_PATH . '/docs/commands.txt'),
  );
  $items['docs-api'] = array(
    'description' => dt('Drush API'),
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(DRUSH_BASE_PATH . '/docs/drush.api.php'),
  );
  $items['docs-context'] = array(
    'description' => dt('Drush Contexts'),
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(DRUSH_BASE_PATH . '/docs/context.txt'),
  );
  $items['docs-examplescript'] = array(
    'description' => dt('Example drush script'),
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(DRUSH_BASE_PATH . '/examples/helloworld.script'),
  );
  $items['docs-examplecommand'] = array(
    'description' => dt('Example drush command file.'),
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(DRUSH_BASE_PATH . '/examples/sandwich.drush.inc'),
  );
  $items['docs-policy'] = array(
    'description' => dt('Example policy file'),
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(DRUSH_BASE_PATH . '/examples/policy.drush.inc'),
  );

  return $items;
}
