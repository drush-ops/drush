<?php

namespace Drush\Drupal\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Sql\SqlBase;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A sql-sanitize plugin.
 */
class SanitizeUserTableCommands extends DrushCommands implements SanitizePluginInterface
{
    protected $database;
    protected $passwordHasher;
    protected $entityTypeManager;

    public function __construct($database, $passwordHasher, $entityTypeManager)
    {
        $this->database = $database;
        $this->passwordHasher = $passwordHasher;
        $this->entityTypeManager = $entityTypeManager;
    }

    /**
     * Sanitize emails and passwords. This also an example of how to write a
     * database sanitizer for sql-sync.
     *
     * @hook post-command sql-sanitize
     *
     * @inheritdoc
     */
    public function sanitize($result, CommandData $commandData): void
    {
        $options = $commandData->options();
        $query = $this->database->update('users_field_data')->condition('uid', 0, '>');
        $messages = [];

        // Sanitize passwords.
        if ($this->isEnabled($options['sanitize-password'])) {
            $password = $options['sanitize-password'];
            if (is_null($password)) {
                $password = StringUtils::generatePassword();
            }

            // Mimic Drupal's /scripts/password-hash.sh
            $hash = $this->passwordHasher->hash($password);
            $query->fields(['pass' => $hash]);
            $messages[] = dt('User passwords sanitized.');
        }

        // Sanitize email addresses.
        if ($this->isEnabled($options['sanitize-email'])) {
            if (strpos($options['sanitize-email'], '%') !== false) {
                // We need a different sanitization query for MSSQL, Postgres and Mysql.
                $sql = SqlBase::create($commandData->input()->getOptions());
                $db_driver = $sql->scheme();
                if ($db_driver == 'pgsql') {
                    $email_map = ['%uid' => "' || uid || '", '%mail' => "' || replace(mail, '@', '_') || '", '%name' => "' || replace(name, ' ', '_') || '"];
                    $new_mail =  "'" . str_replace(array_keys($email_map), array_values($email_map), $options['sanitize-email']) . "'";
                } elseif ($db_driver == 'mssql') {
                    $email_map = ['%uid' => "' + uid + '", '%mail' => "' + replace(mail, '@', '_') + '", '%name' => "' + replace(name, ' ', '_') + '"];
                    $new_mail =  "'" . str_replace(array_keys($email_map), array_values($email_map), $options['sanitize-email']) . "'";
                } else {
                    $email_map = ['%uid' => "', uid, '", '%mail' => "', replace(mail, '@', '_'), '", '%name' => "', replace(name, ' ', '_'), '"];
                    $new_mail =  "concat('" . str_replace(array_keys($email_map), array_values($email_map), $options['sanitize-email']) . "')";
                }
                $query->expression('mail', $new_mail);
                $query->expression('init', $new_mail);
            } else {
                $query->fields(['mail' => $options['sanitize-email']]);
            }
            $messages[] = dt('User emails sanitized.');
        }

        if ($messages) {
            $query->execute();
            $this->entityTypeManager->getStorage('user')->resetCache();
            foreach ($messages as $message) {
                $this->logger()->success($message);
            }
        }
    }

    /**
     * @hook option sql-sanitize
     * @option sanitize-email The pattern for test email addresses in the
     *   sanitization operation, or <info>no</info> to keep email addresses unchanged. May
     *   contain replacement patterns <info>%uid</info>, <info>%mail</info> or <info>%name</info>.
     * @option sanitize-password
     *   By default, passwords are randomized. Specify <info>no</info> to disable that. Specify any other value to set all passwords
     *   to that value.
     */
    public function options($options = ['sanitize-email' => 'user+%uid@localhost.localdomain', 'sanitize-password' => null]): void
    {
    }

    /**
     * @hook on-event sql-sanitize-confirms
     *
     * @inheritdoc
     */
    public function messages(&$messages, InputInterface $input): void
    {
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
     */
    protected function isEnabled($value): bool
    {
        return $value != 'no' && $value != '0';
    }
}
