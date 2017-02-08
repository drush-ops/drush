<?php
namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;
use Drush\Sql\SqlBase;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A sql-sanitize plugin.
 */
class SanitizeUserTableCommands extends DrushCommands implements SqlSanitizePluginInterface {

  /**
   * Sanitize usernames and passwords. This also an example of how to write a
   * database sanitizer for sql-sync.
   *
   * @hook post-command sql-sanitize
   *
   * @inheritdoc
   */
  public function sanitize($result, CommandData $commandData) {
    $options = $commandData->options();
    $query = Database::getConnection()->update('users_field_data')
      ->condition('uid', 0, '>');
    $messages = [];

    // Sanitize passwords.
    if ($this->isEnabled($options['sanitize-password'])) {
      // D8+. Mimic Drupal's /scripts/password-hash.sh
      drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
      $password_hasher = \Drupal::service('password');
      $hash = $password_hasher->hash($options['sanitize-password']);
      $query->fields(['pass' => $hash]);
      $messages[] = dt('User passwords sanitized.');
    }

    // Sanitize email addresses.
    if ($this->isEnabled($options['sanitize-email'])) {
      if (strpos($options['sanitize-email'], '%') !== FALSE) {
        // We need a different sanitization query for MSSQL, Postgres and Mysql.
        $sql = SqlBase::create($commandData->input()->getOptions());
        $db_driver = $sql->scheme();
        if ($db_driver == 'pgsql') {
          $email_map = array('%uid' => "' || uid || '", '%mail' => "' || replace(mail, '@', '_') || '", '%name' => "' || replace(name, ' ', '_') || '");
          $new_mail =  "'" . str_replace(array_keys($email_map), array_values($email_map), $options['sanitize-email']) . "'";
        }
        elseif ($db_driver == 'mssql') {
          $email_map = array('%uid' => "' + uid + '", '%mail' => "' + replace(mail, '@', '_') + '", '%name' => "' + replace(name, ' ', '_') + '");
          $new_mail =  "'" . str_replace(array_keys($email_map), array_values($email_map), $options['sanitize-email']) . "'";
        }
        else {
          $email_map = array('%uid' => "', uid, '", '%mail' => "', replace(mail, '@', '_'), '", '%name' => "', replace(name, ' ', '_'), '");
          $new_mail =  "concat('" . str_replace(array_keys($email_map), array_values($email_map), $options['sanitize-email']) . "')";
        }
        $query->expression('mail', $new_mail);
      }
      else {
        $query->fields(['mail' => $options['sanitize-email']]);
      }
      $messages[] = dt('User emails sanitized.');
    }

    if ($messages) {
      $query->execute();
      foreach ($messages as $message) {
        $this->logger()->success($message);
      }
    }
  }

  /**
   * @hook on-event sql-sanitize-confirms
   *
   * @inheritdoc
   */
  public function messages(&$messages, InputInterface $input) {
    $options = $input->getOptions();
    if ($this->isEnabled($options['sanitize-password'])) {
      $messages[] = dt('Sanitize user passwords.');
    }
    if ($this->isEnabled($options['sanitize-email'])) {
      $messages[] = dt('Sanitize user emails.');
    }
  }

  /**
   * Test an option value to see if it is disabled.
   * @param $value
   * @return bool
   */
  protected function isEnabled($value) {
    return $value != 'no' && $value != '0';
  }
}

