<?php
namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;

/**
 * This class is a good example of how to build a sql-sanitize extension.
 */
class SanitizeCommentsCommands extends DrushCommands {

  /**
   * Sanitize comment names from the DB. This also an example of how to write a
   * database sanitizer for sql-sync.
   *
   * @param $result Exit code from the main operation for this command.
   * @param $commandData Information about the current request.
   *
   * @hook post-command sql-sanitize
   */
  public function sanitize($result, CommandData $commandData) {
    if ($this->applies()) {
      //Update anon.
      Database::getConnection()->update('comment_field_data')
        ->fields([
          'name' => 'Anonymous',
          'mail' => '',
          'homepage' => 'http://example.com'
        ])
        ->condition('uid', 0)
        ->execute();

      // Update auth.
      Database::getConnection()->update('comment_field_data')
        ->expression('name', "CONCAT('User', `uid`)")
        ->expression('mail', "CONCAT('user+', `uid`, '@example.com')")
        ->fields(['homepage' => 'http://example.com'])
        ->condition('uid', 1, '>=')
        ->execute();
      $this->logger()->success(dt('Comment display names and emails removed.'));
    }
  }

  /**
   * @hook on-event sql-sanitize-confirms
   * @param $messages An array of messages to show during confirmation.
   * @param $input The effective commandline input for this request.
   */
  public function messages(&$messages, InputInterface $input) {
    if ($this->applies()) {
      $messages[] = dt('Remove comment display names and emails.');
    }
  }

  protected function applies() {
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
    return \Drupal::moduleHandler()->moduleExists('comment');
  }
}

