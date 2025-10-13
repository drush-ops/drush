<?php

declare(strict_types=1);

namespace Drush\Commands\user;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\user\Entity\User;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Print information about the specified user(s).',
    aliases: ['uinf', 'user-information'],
)]
#[CLI\FieldLabels(labels: self::INF_LABELS)]
#[CLI\DefaultTableFields(fields: self::INF_DEFAULT_FIELDS)]
#[CLI\FilterDefaultField(field: 'name')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class UserInformationCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use UserTrait;

    public const NAME = 'user:information';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
        protected DateFormatterInterface $dateFormatter,
        protected readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('names', InputArgument::OPTIONAL, 'A comma delimited list of user names.', '')
            ->addOption('uid', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of user ids to lookup (an alternative to names).')
            ->addOption('mail', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of emails to lookup (an alternative to names).')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml', 'table')
            ->addUsage('user:information someguy,somegal')
            ->addUsage('user:information --mail=someguy@somegal.com')
            ->addUsage('user:information --uid=5')
            ->addUsage('uinf --uid=$(drush sqlq "SELECT GROUP_CONCAT(entity_id) FROM user__roles WHERE roles_target_id = \'administrator\'")');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $names = $input->getArgument('names');
        $uidOption = $input->getOption('uid');
        $mailOption = $input->getOption('mail');

        $accounts = [];
        if ($mails = StringUtils::csvToArray($mailOption)) {
            foreach ($mails as $mail) {
                if ($account = user_load_by_mail($mail)) {
                    $accounts[$account->id()] = $account;
                }
            }
        }
        if ($uids = StringUtils::csvToArray($uidOption)) {
            if ($loaded = User::loadMultiple($uids)) {
                $accounts += $loaded;
            }
        }
        if ($names = StringUtils::csvToArray($names)) {
            foreach ($names as $name) {
                if ($account = user_load_by_name($name)) {
                    $accounts[$account->id()] = $account;
                }
            }
        }
        if ($accounts === []) {
            throw new \Exception('Unable to find a matching user');
        }

        foreach ($accounts as $id => $account) {
            $outputs[$id] = $this->infoArray($account);
        }

        $result = new RowsOfFields($outputs);
        $result->addRendererFunction([$this, 'renderRolesCell']);
        return $result;
    }

    public function renderRolesCell($key, $cellData, FormatterOptions $options)
    {
        if (is_array($cellData)) {
            return implode("\n", $cellData);
        }
        return $cellData;
    }

    /**
     * A flatter and simpler array presentation of a Drupal $user object.
     */
    public function infoArray($account): array
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
}
