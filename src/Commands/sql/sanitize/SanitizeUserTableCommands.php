<?php

declare(strict_types=1);

namespace Drush\Commands\sql\sanitize;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Sql\SqlBase;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A sql:sanitize plugin.
 */
final class SanitizeUserTableCommands extends DrushCommands implements SanitizePluginInterface
{
    use AutowireTrait;

    public function __construct(
        protected Connection $database,
        protected PasswordInterface $passwordHasher,
        protected EntityTypeManagerInterface $entityTypeManager
    ) {
        parent::__construct();
    }

    /**
     * Sanitize emails and passwords. This also an example of how to write a
     * database sanitizer for sql:sync.
     */
    #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: SanitizeCommands::SANITIZE)]
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
            if (str_contains($options['sanitize-email'], '%')) {
                // We need a different sanitization query for MSSQL, Postgres and Mysql.
                $sql = SqlBase::create($commandData->input()->getOptions());
                $db_driver = $sql->scheme();
                if ($db_driver === 'pgsql') {
                    $email_map = ['%uid' => "' || uid || '", '%mail' => "' || replace(mail, '@', '_') || '", '%name' => "' || replace(name, ' ', '_') || '"];
                    $new_mail =  "'" . str_replace(array_keys($email_map), array_values($email_map), $options['sanitize-email']) . "'";
                } elseif ($db_driver === 'mssql') {
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

        if (!empty($options['ignored-roles'])) {
            $roles = explode(',', $options['ignored-roles']);
            /** @var SelectInterface $roles_query */
            $roles_query = $this->database->select('user__roles', 'ur');
            $roles_query
                ->condition('roles_target_id', $roles, 'IN')
                ->fields('ur', ['entity_id']);
            $roles_query_results = $roles_query->execute();
            $ignored_users = $roles_query_results->fetchCol();

            if (!empty($ignored_users)) {
                $query->condition('uid', $ignored_users, 'NOT IN');
                $messages[] = dt('User emails and passwords for the specified roles preserved.');
            }
        }

        if ($messages) {
            $query->execute();
            $this->entityTypeManager->getStorage('user')->resetCache();
            foreach ($messages as $message) {
                $this->logger()->success($message);
            }
        }
    }

    #[CLI\Hook(type: HookManager::OPTION_HOOK, target: SanitizeCommands::SANITIZE)]
    #[CLI\Option(name: 'sanitize-email', description: 'The pattern for test email addresses in the sanitization operation, or <info>no</info> to keep email addresses unchanged. May contain replacement patterns <info>%uid</info>, <info>%mail</info> or <info>%name</info>.')]
    #[CLI\Option(name: 'sanitize-password', description: 'By default, passwords are randomized. Specify <info>no</info> to disable that. Specify any other value to set all passwords to that value.')]
    #[CLI\Option(name: 'ignored-roles', description: 'A comma delimited list of roles. Users with at least one of the roles will be exempt from sanitization.')]
    public function options($options = ['sanitize-email' => 'user+%uid@localhost.localdomain', 'sanitize-password' => null, 'ignored-roles' => null]): void
    {
    }

    #[CLI\Hook(type: HookManager::ON_EVENT, target: SanitizeCommands::CONFIRMS)]
    public function messages(&$messages, InputInterface $input): void
    {
        $options = $input->getOptions();
        if ($this->isEnabled($options['sanitize-password'])) {
            $messages[] = dt('Sanitize user passwords.');
        }
        if ($this->isEnabled($options['sanitize-email'])) {
            $messages[] = dt('Sanitize user emails.');
        }
        if (in_array('ignored-roles', $options)) {
            $messages[] = dt('Preserve user emails and passwords for the specified roles.');
        }
    }

    /**
     * Test an option value to see if it is disabled.
     */
    protected function isEnabled(?string $value): bool
    {
        return $value != 'no' && $value != '0';
    }
}
