<?php

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
