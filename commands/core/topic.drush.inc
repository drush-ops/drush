<?php

/**
 * @file
 *   Topic command and associated hooks.
 */

/**
 * Implementation of hook_drush_command().
 *
 * @return
 *   An associative array describing your command(s).
 */
function topic_drush_command() {
  $items['core-topic'] = array(
    'description' => 'Read detailed documentation on a given topic.',
    'arguments' => array(
      'topic name' => 'The name of the topic you wish to view. If omitted, list all topic descriptions (and names in parenthesis).',
    ),
    'examples' => array(
      'drush topic' => 'Show all available topics.',
      'drush topic docs-context' => 'Show documentation for the drush context API',
      'drush docs-context' => 'Show documentation for the drush context API',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'aliases' => array('topic'),
    'topics' => array('docs-readme'),
  );

  return $items;
}

/**
 * Implement hook_drush_help_alter(). Show 'Topics' section on help detail.
 */
function topic_drush_help_alter($command) {
  $implemented = drush_get_commands();
  foreach ($command['topics'] as $topic_name) {
    // We have a related topic. Inject into the $command so the topic displays.
    $command['sections']['topic_section'] = 'Topics';
    $command['topic_section'][$topic_name] = $implemented[$topic_name]['description'];
  }
}

/**
 * A command callback.
 *
 * Show a choice list of available topics and then dispatch to the respective command.
 *
 * @param string $topic_name
 *   A command name.
 */
function drush_topic_core_topic($topic_name = NULL) {
  $commands = drush_get_commands();
  if (is_null($topic_name)) {
    // Show choice list.
    foreach (drush_get_topics() as $key => $topic) {
      $choices[$key] = $topic['description'];
    }
    natcasesort($choices);
    if (!$topic_name = drush_choice($choices, dt('Choose a topic'), '!value (!key)')) {
      return;
    }
  }
  // If the topic name is not found, check for
  // "docs-$topic_name".  This allows users to be
  // just a bit lazy when selecting core topics by name.
  if (!isset($commands[$topic_name]) && isset($commands["docs-$topic_name"])) {
    $topic_name = "docs-$topic_name";
  }
  return drush_dispatch($commands[$topic_name]);
}

/**
 * Retrieve all defined topics
 */
function drush_get_topics() {
  $commands = drush_get_commands();
  foreach ($commands as $key => $command) {
    if (!empty($command['topic']) && empty($command['is_alias'])) {
      $topics[$key] = $command;
    }
  }
  return $topics;
}
