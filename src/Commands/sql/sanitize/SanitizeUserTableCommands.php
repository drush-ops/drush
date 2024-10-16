<?php

declare(strict_types=1);

namespace Drush\Commands\sql\sanitize;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
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
        protected EntityTypeManagerInterface $entityTypeManager,
        protected EntityFieldManagerInterface $entityFieldManager
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

        // Sanitize username.
        if ($this->isEnabled($options['sanitize-username'])) {
            [$name_table, $name_column] = $this->getFieldTableDetails('user', 'name');
            [$uid_table, $uid_column] = $this->getFieldTableDetails('user', 'uid');
            assert($uid_table === $name_table);

            // Updates usernames to the pattern user_%uid.
            $query
                ->condition($uid_column, 0, '>')
                ->expression($name_column, "CONCAT('user_', $uid_column)");

            $messages[] = dt("Usernames sanitized.");
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
    #[CLI\Option(name: 'sanitize-username', description: 'Sanitizes usernames replacing the originals with user_UID.')]
    #[CLI\Option(name: 'ignored-roles', description: 'A comma delimited list of roles. Users with at least one of the roles will be exempt from sanitization.')]
    public function options($options = ['sanitize-email' => 'user+%uid@localhost.localdomain', 'sanitize-password' => null, 'sanitize-username' => 'no', 'ignored-roles' => null]): void
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
        if ($this->isEnabled($options['sanitize-username'])) {
            $messages[] = dt('Sanitize usernames.');
        }
        if (in_array('ignored-roles', $options)) {
            $messages[] = dt('Preserve user emails and passwords for the specified roles.');
        }
    }

    /**
     * Gets database details for a given field.
     *
     * It returns the field table name and main property column name.
     *
     * @param string $entity_type_id
     *   The entity type ID the field's attached to.
     * @param string $field_name
     *   The field name.
     *
     * @return array
     *   An indexed array, containing:
     *   - the table name;
     *   - the column name.
     */
    protected function getFieldTableDetails(string $entity_type_id, string $field_name): array
    {
        $storage = $this->entityTypeManager->getStorage($entity_type_id);
        if (!$storage instanceof SqlEntityStorageInterface) {
            $context = ['!entity_type_id' => $entity_type_id];
            throw new \Exception(dt("Unable to get !entity_type_id table mapping details, its storage doesn't implement \Drupal\Core\Entity\Sql\SqlEntityStorageInterface.", $context));
        }
        $mapping = $storage->getTableMapping();
        $table = $mapping->getFieldTableName($field_name);
        $columns = $mapping->getColumnNames($field_name);
        $definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
        $main_property = $definitions[$field_name]->getMainPropertyName();

        return [$table, $columns[$main_property]];
    }

    /**
     * Test an option value to see if it is disabled.
     */
    protected function isEnabled(?string $value): bool
    {
        return $value != 'no' && $value != '0';
    }
}
