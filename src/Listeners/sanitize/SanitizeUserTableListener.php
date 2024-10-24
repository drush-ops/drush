<?php

declare(strict_types=1);

namespace Drush\Listeners\sanitize;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\sql\sanitize\SanitizeCommand;
use Drush\Event\ConsoleDefinitionsEvent;
use Drush\Event\SanitizeConfirmsEvent;
use Drush\Sql\SqlBase;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Sanitize emails and passwords. This also an example of how to write a
 *  database sanitizer for sql:sync.
 */
#[AsEventListener(method: 'onDefinition')]
#[AsEventListener(method: 'onSanitizeConfirm')]
#[AsEventListener(method: 'onConsoleTerminate')]
final class SanitizeUserTableListener
{
    use AutowireTrait;

    public function __construct(
        protected Connection $database,
        protected PasswordInterface $passwordHasher,
        protected EntityTypeManagerInterface $entityTypeManager,
        protected LoggerInterface $logger,
    ) {
    }

    public function onDefinition(ConsoleDefinitionsEvent $event): void
    {
        foreach ($event->getApplication()->all() as $id => $command) {
            if ($command->getName() === SanitizeCommand::NAME) {
                $command->addOption(
                    'sanitize-email',
                    null,
                    InputOption::VALUE_REQUIRED,
                    'The pattern for test email addresses in the sanitization operation, or <info>no</info> to keep email addresses unchanged. May contain replacement patterns <info>%uid</info>, <info>%mail</info> or <info>%name</info>.',
                    'user+%uid@localhost.localdomain'
                )
                    ->addOption('sanitize-password', null, InputOption::VALUE_REQUIRED, 'By default, passwords are randomized. Specify <info>no</info> to disable that. Specify any other value to set all passwords to that value.')
                    ->addOption('ignored-roles', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of roles. Users with at least one of the roles will be exempt from sanitization.');
            }
        }
    }

    public function onSanitizeConfirm(SanitizeConfirmsEvent $event): void
    {
        $options = $event->getInput()->getOptions();
        if ($this->isEnabled($options['sanitize-password'])) {
            $event->addMessage(dt('Sanitize user passwords.'));
        }
        if ($this->isEnabled($options['sanitize-email'])) {
            $event->addMessage(dt('Sanitize user emails.'));
        }
        if (in_array('ignored-roles', $options)) {
            $event->addMessage(dt('Preserve user emails and passwords for the specified roles.'));
        }
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $options = $event->getInput()->getOptions();
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
                $sql = SqlBase::create($event->getInput()->getOptions());
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
                $this->logger->success($message);
            }
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
