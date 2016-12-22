<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;


class TopicCommands extends DrushCommands {

  /**
   * Read detailed documentation on a given topic.
   *
   * @command core-topic
   * @param $topic_name  The name of the topic you wish to view. If omitted, list all topic descriptions (and names in parenthesis).
   * @usage drush topic
   *   Show all available topics.
   * @usage drush topic docs-context
   *   Show documentation for the drush context API
   * @usage drush docs-context
   *   Show documentation for the drush context API
   * @remote-tty
   * @aliases topic
   * @topics docs-readme
   * @complete \Drush\Commands\core\TopicCommands::complete
   */
  public function topic($topic_name = '') {
    $commands = drush_get_commands();
    $topics = self::getAllTopics();
    if (!empty($topic_name)) {
      foreach (self::getAllTopics() as $key => $topic) {
        if (strstr($key, $topic_name) === FALSE) {
          unset($topics[$key]);
        }
      }
    }
    if (empty($topics)) {
      throw new \Exception(dt("!topic topic not found.", array('!topic' => $topic_name)));
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
   * Retrieve all defined topics
   */
  static function getAllTopics() {
    $commands = drush_get_commands();
    foreach ($commands as $key => $command) {
      if (!empty($command['topic']) && empty($command['is_alias'])) {
        $topics[$key] = $command;
      }
    }
    return $topics;
  }

  public function complete() {
    return array('values' => array_keys(drush_get_topics()));
  }
}
