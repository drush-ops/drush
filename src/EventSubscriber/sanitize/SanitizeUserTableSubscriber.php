<?php

declare(strict_types=1);

namespace Drush\EventSubscriber\sanitize;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Event\SanitizeConfirmsEvent;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sanitizes user emails and passwords.
 *
 * Also an example of a sanitizer that adds options to sql:sanitize.
 * Registered only when the user module is installed.
 */
final class SanitizeUserTableSubscriber implements EventSubscriberInterface
{
    use SanitizeOptionsTrait;
    use StringTranslationTrait;

    /**
     * Replacement patterns for --sanitize-email, and their SQL.
     */
    private const array EMAIL_TOKENS = [
        '%uid' => 'uid',
        '%mail' => "REPLACE(mail, '@', '_')",
        '%name' => "REPLACE(name, ' ', '_')",
    ];

    public function __construct(
        private readonly Connection $database,
        private readonly PasswordInterface $passwordHasher,
        private readonly PasswordGeneratorInterface $passwordGenerator,
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onCommand',
            SanitizeConfirmsEvent::class => 'onSanitizeConfirm',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $this->addSanitizeOptions($event, [
            new InputOption(
                'sanitize-email',
                null,
                InputOption::VALUE_REQUIRED,
                'The pattern for test email addresses in the sanitization operation, or <info>no</info> to keep email addresses unchanged. May contain replacement patterns <info>%uid</info>, <info>%mail</info> or <info>%name</info>.',
                'user+%uid@localhost.localdomain',
            ),
            new InputOption('sanitize-password', null, InputOption::VALUE_REQUIRED, 'By default, passwords are randomized. Specify <info>no</info> to disable that. Specify any other value to set all passwords to that value.'),
            new InputOption('ignored-roles', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of roles. Users with at least one of the roles will be exempt from sanitization.'),
        ]);
    }

    public function onSanitizeConfirm(SanitizeConfirmsEvent $event): void
    {
        $input = $event->getInput();
        if ($this->isEnabled($input->getOption('sanitize-password'))) {
            $event->addMessage((string) $this->t('Sanitize user passwords.'));
        }
        if ($this->isEnabled($input->getOption('sanitize-email'))) {
            $event->addMessage((string) $this->t('Sanitize user emails.'));
        }
        if ($input->getOption('ignored-roles')) {
            $event->addMessage((string) $this->t('Preserve user emails and passwords for the specified roles.'));
        }
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if (!$this->isSanitizing($event)) {
            return;
        }

        $input = $event->getInput();
        $query = $this->database->update('users_field_data')->condition('uid', 0, '>');
        // Update::fields() replaces earlier calls, so collect the values.
        $fields = $messages = [];

        $password = $input->getOption('sanitize-password');
        if ($this->isEnabled($password)) {
            $fields['pass'] = $this->passwordHasher->hash($password ?? $this->passwordGenerator->generate());
            $messages[] = $this->t('User passwords sanitized.');
        }

        $email = $input->getOption('sanitize-email');
        if ($this->isEnabled($email)) {
            [$expression, $arguments] = $this->emailExpression($email);
            if ($expression) {
                // init first: MySQL assigns left to right, and %mail must
                // read the original address both times.
                $query->expression('init', $expression, $arguments);
                $query->expression('mail', $expression, $arguments);
            } else {
                $fields['mail'] = $email;
            }
            $messages[] = $this->t('User emails sanitized.');
        }

        if ($messages && $roles = StringUtils::csvToArray($input->getOption('ignored-roles'))) {
            $ignored = $this->database->select('user__roles', 'ur')
                ->fields('ur', ['entity_id'])
                ->condition('roles_target_id', $roles, 'IN')
                ->execute()
                ->fetchCol();
            if ($ignored) {
                $query->condition('uid', $ignored, 'NOT IN');
                $messages[] = $this->t('User emails and passwords for the specified roles preserved.');
            }
        }

        if ($messages) {
            $query->fields($fields)->execute();
            $this->entityTypeManager->getStorage('user')->resetCache();
            $event->getOutput()->writeln(array_map(strval(...), $messages));
        }
    }

    /**
     * Builds the SQL for an email pattern containing replacement patterns.
     *
     * @return array{?string, array<string, string>}
     *   The expression and its arguments. The expression is NULL when the
     *   pattern is a fixed address.
     */
    private function emailExpression(string $pattern): array
    {
        $tokens = implode('|', array_keys(self::EMAIL_TOKENS));
        $parts = preg_split("/($tokens)/", $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (!array_intersect($parts, array_keys(self::EMAIL_TOKENS))) {
            return [null, []];
        }
        $sql = $arguments = [];
        foreach ($parts as $delta => $part) {
            if (isset(self::EMAIL_TOKENS[$part])) {
                $sql[] = self::EMAIL_TOKENS[$part];
            } else {
                $sql[] = ":sanitize_email_$delta";
                $arguments[":sanitize_email_$delta"] = $part;
            }
        }
        // CONCAT() exists on MySQL, PostgreSQL and (through core) SQLite.
        return ['CONCAT(' . implode(', ', $sql) . ')', $arguments];
    }

    /**
     * An option set to "no" or "0" turns its operation off.
     */
    private function isEnabled(?string $value): bool
    {
        return $value !== 'no' && $value !== '0';
    }
}
