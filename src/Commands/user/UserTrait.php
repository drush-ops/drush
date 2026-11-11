<?php

declare(strict_types=1);

namespace Drush\Commands\user;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Drush\Commands\OutputFormat;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Helpers shared by the user commands.
 *
 * Classes using getAccounts() inject EntityTypeManagerInterface as
 * $this->entityTypeManager and LoggerInterface as $this->logger. The date
 * formatter that infoArray() needs is passed in, so that only the commands
 * which print account information have to inject it.
 */
trait UserTrait
{
    use StringTranslationTrait;

    const array INF_LABELS = [
        'uid' => 'User ID',
        'name' => 'User name',
        'pass' => 'Password',
        'mail' => 'User mail',
        'theme' => 'User theme',
        'signature' => 'Signature',
        'signature_format' => 'Signature format',
        'user_created' => 'User created',
        'created' => 'Created',
        'user_access' => 'User last access',
        'access' => 'Last access',
        'user_login' => 'User last login',
        'login' => 'Last login',
        'user_status' => 'User status',
        'status' => 'Status',
        'timezone' => 'Time zone',
        'picture' => 'User picture',
        'init' => 'Initial user mail',
        'roles' => 'User roles',
        'group_audience' => 'Group Audience',
        'langcode' => 'Language code',
        'uuid' => 'Uuid',
    ];

    const array INF_DEFAULT_FIELDS = ['uid', 'name', 'mail', 'roles', 'user_status'];

    /**
     * Loads the accounts selected by a names argument and the --uid and
     * --mail options, each a comma delimited list.
     *
     * Logs a warning for every value that matches no account and throws when
     * none of them did.
     *
     * @return UserInterface[]
     *   The accounts, keyed by uid.
     */
    protected function getAccounts(string $names, ?string $uid = null, ?string $mail = null): array
    {
        $storage = $this->entityTypeManager->getStorage('user');
        $accounts = [];
        foreach (StringUtils::csvToArray($mail) as $value) {
            $matches = $storage->loadByProperties(['mail' => $value]);
            if ($account = reset($matches)) {
                $accounts[$account->id()] = $account;
            } else {
                $this->logger->warning((string) $this->t('Unable to load user: @mail', ['@mail' => $value]));
            }
        }
        foreach (StringUtils::csvToArray($uid) as $value) {
            if ($account = $storage->load($value)) {
                $accounts[$account->id()] = $account;
            } else {
                $this->logger->warning((string) $this->t('Unable to load user: @uid', ['@uid' => $value]));
            }
        }
        foreach (StringUtils::csvToArray($names) as $value) {
            $matches = $storage->loadByProperties(['name' => $value]);
            if ($account = reset($matches)) {
                $accounts[$account->id()] = $account;
            } else {
                $this->logger->warning((string) $this->t('Unable to load user: @user', ['@user' => $value]));
            }
        }
        if ($accounts === []) {
            throw new \InvalidArgumentException((string) $this->t('Unable to find any matching user'));
        }

        return $accounts;
    }

    /**
     * A flatter and simpler array presentation of a Drupal $user object.
     */
    public function infoArray(UserInterface $account, DateFormatterInterface $dateFormatter): array
    {
        return [
            'uid' => $account->id(),
            'name' => $account->getAccountName(),
            'pass' => $account->getPassword(),
            'mail' => $account->getEmail(),
            'user_created' => $account->getCreatedTime(),
            'created' => $dateFormatter->format($account->getCreatedTime()),
            'user_access' => $account->getLastAccessedTime(),
            'access' => $dateFormatter->format($account->getLastAccessedTime()),
            'user_login' => $account->getLastLoginTime(),
            'login' => $dateFormatter->format($account->getLastLoginTime()),
            'user_status' => $account->get('status')->value,
            'status' => $account->isActive() ? 'active' : 'blocked',
            'timezone' => $account->getTimeZone(),
            'roles' => $account->getRoles(),
            'langcode' => $account->getPreferredLangcode(),
            'uuid' => $account->uuid->value,
        ];
    }

    /**
     * Writes account information as a table (default), json or yaml.
     *
     * The table shows INF_DEFAULT_FIELDS unless --fields names others; json
     * and yaml carry the whole infoArray() per account, keyed by uid, unless
     * --fields narrows them.
     *
     * @param UserInterface[] $accounts
     */
    protected function writeAccounts(OutputInterface $output, array $accounts, DateFormatterInterface $dateFormatter, ?OutputFormat $format = null, ?string $fields = null): void
    {
        $rows = [];
        foreach ($accounts as $id => $account) {
            $rows[$id] = $this->infoArray($account, $dateFormatter);
        }

        $selected = $this->selectFields($fields, $format === null ? self::INF_DEFAULT_FIELDS : array_keys(self::INF_LABELS));
        if ($fields !== null) {
            foreach ($rows as &$row) {
                $row = array_intersect_key($row, array_flip($selected));
            }
            unset($row);
        }

        $result = match ($format) {
            OutputFormat::Json => json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => Yaml::encode($rows),
            default => $this->renderAccountsTable($rows, $selected),
        };
        $output->writeln($result, OutputInterface::OUTPUT_RAW);
    }

    /**
     * Resolves a --fields value against INF_LABELS; '*' selects every field.
     *
     * @return string[]
     */
    protected function selectFields(?string $fields, array $default): array
    {
        if ($fields === null || $fields === '') {
            return $default;
        }
        if ($fields === '*') {
            return array_keys(self::INF_LABELS);
        }
        $selected = StringUtils::csvToArray($fields);
        if ($unknown = array_diff($selected, array_keys(self::INF_LABELS))) {
            throw new \InvalidArgumentException((string) $this->t('Unknown field(s): @fields', ['@fields' => implode(', ', $unknown)]));
        }
        return $selected;
    }

    protected function renderAccountsTable(array $rows, array $fields): string
    {
        $tableRows = [];
        foreach ($rows as $row) {
            $tableRow = [];
            foreach ($fields as $field) {
                $value = $row[$field] ?? '';
                $tableRow[] = is_array($value) ? implode(', ', $value) : (string) $value;
            }
            $tableRows[] = $tableRow;
        }
        $buffer = new BufferedOutput();
        $table = new Table($buffer);
        $table
            ->setHeaders(array_map(fn(string $field) => self::INF_LABELS[$field], $fields))
            ->setRows($tableRows)
            ->render();
        return $buffer->fetch();
    }
}
