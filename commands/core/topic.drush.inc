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
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'remote-tty' => TRUE,
    'aliases' => array('topic', 'core:topic'),
    'topics' => array('docs-readme'),
  );

  return $items;
}

/**
 * Implement hook_drush_help_alter(). Show 'Topics' section on help detail.
 */
function topic_drush_help_alter(&$command) {
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
  $topics = drush_get_topics();
  if (isset($topic_name)) {
    foreach (drush_get_topics() as $key => $topic) {
      if (strstr($key, $topic_name) === FALSE) {
        unset($topics[$key]);
      }
    }
  }
  if (empty($topics)) {
    return drush_set_error('DRUSH_NO_SUCH_TOPIC', dt("No topics on !topic found.", array('!topic' => $topic_name)));
  }
  if (count($topics) > 1) {
    // Show choice list.
    foreach ($topics as $key => $topic) {
      $choices[$key] = $topic['description'];
    }
    natcasesort($choices);
    if (!$topic_name = drush_choice($choices, dt('Choose a topic'), '!value (!key)', array(5))) {
      return drush_user_abort();
    }
  }
  else {
    $keys = array_keys($topics);
    $topic_name = array_pop($keys);
  }
  return drush_dispatch($commands[$topic_name]);
}

/**
 * A command argument complete callback.
 *
 * @return
 *   Available topic keys.
 */
function topic_core_topic_complete() {
  return array('values' => array_keys(drush_get_topics()));
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
