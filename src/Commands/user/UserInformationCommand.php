<?php

declare(strict_types=1);

namespace Drush\Commands\user;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OutputFormat;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Print information about the specified user(s).',
    aliases: ['uinf', 'user-information'],
    usages: [
        'user:information someguy,somegal',
        'user:information --mail=someguy@somegal.com',
        'user:information --uid=5',
        'user:information someguy --fields=uid,name,created --format=json',
        'uinf --uid=$(drush sqlq "SELECT GROUP_CONCAT(entity_id) FROM user__roles WHERE roles_target_id = \'administrator\'")',
    ],
)]
class UserInformationCommand
{
    use AutowireTrait;
    use UserTrait;

    public const string NAME = 'user:information';

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

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected DateFormatterInterface $dateFormatter,
        #[Autowire(service: 'logger.channel.default')]
        protected LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('A comma delimited list of user names.')]
        string $names = '',
        #[Option('A comma delimited list of user ids to lookup (an alternative to names).')]
        ?string $uid = null,
        #[Option('A comma delimited list of emails to lookup (an alternative to names).')]
        ?string $mail = null,
        #[Option('A comma delimited list of fields to show. Use * for all. Defaults to uid,name,mail,roles,user_status for the table and to all fields for json and yaml.')]
        ?string $fields = null,
        #[Option('An alternative output format such as json or yaml. Defaults to table.')]
        ?OutputFormat $format = null,
    ): int {
        $rows = [];
        foreach ($this->getAccounts($names, $uid, $mail) as $id => $account) {
            $rows[$id] = $this->infoArray($account);
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
            default => $this->renderTable($rows, $selected),
        };
        $output->writeln($result, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    /**
     * A flatter and simpler array presentation of a Drupal $user object.
     */
    public function infoArray(UserInterface $account): array
    {
        return [
            'uid' => $account->id(),
            'name' => $account->getAccountName(),
            'pass' => $account->getPassword(),
            'mail' => $account->getEmail(),
            'user_created' => $account->getCreatedTime(),
            'created' => $this->dateFormatter->format($account->getCreatedTime()),
            'user_access' => $account->getLastAccessedTime(),
            'access' => $this->dateFormatter->format($account->getLastAccessedTime()),
            'user_login' => $account->getLastLoginTime(),
            'login' => $this->dateFormatter->format($account->getLastLoginTime()),
            'user_status' => $account->get('status')->value,
            'status' => $account->isActive() ? 'active' : 'blocked',
            'timezone' => $account->getTimeZone(),
            'roles' => $account->getRoles(),
            'langcode' => $account->getPreferredLangcode(),
            'uuid' => $account->uuid->value,
        ];
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

    protected function renderTable(array $rows, array $fields): string
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
