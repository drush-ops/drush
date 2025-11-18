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
use Drush\Config\DrushConfig;
use Drush\Formatters\FormatterTrait;
use Drush\Style\DrushStyle;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Create a user account.',
    aliases: ['ucrt', 'user-create']
)]
#[CLI\FieldLabels(labels: self::INF_LABELS)]
#[CLI\DefaultTableFields(fields: self::INF_DEFAULT_FIELDS)]
#[CLI\FilterDefaultField(field: 'name')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class UserCreateCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use UserTrait;

    public const string NAME = 'user:create';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
        protected readonly LoggerInterface $logger,
        protected readonly DrushConfig $drushConfig,
        protected DateFormatterInterface $dateFormatter
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the account to add')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'The password for the new account')
            ->addOption('mail', null, InputOption::VALUE_REQUIRED, 'The email address for the new account')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml', 'table')
            ->addUsage("user:create newuser --mail='person@example.com' --password='letmein'")
            ->addUsage("user:create anotheruser --fields=uuid,langcode");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $io = new DrushStyle($input, $output);
        $name = $input->getArgument('name');
        $password = $input->getOption('password');
        $mail = $input->getOption('mail');

        // Validation
        if ($mail) {
            if (user_load_by_mail($mail)) {
                throw new \Exception(sprintf('There is already a user account with the email %s', $mail));
            }
        }
        if (user_load_by_name($name)) {
            throw new \Exception(sprintf('There is already a user account with the name %s', $name));
        }

        $new_user = [
            'name' => $name,
            'pass' => $password,
            'mail' => $mail,
            'access' => '0',
            'status' => 1,
        ];

        if (!$this->drushConfig->simulate()) {
            if ($account = User::create($new_user)) {
                $account->save();
                $io->success(sprintf('Created a new user with uid %s', $account->id()));
                $outputs[$account->id()] = $this->infoArray($account);

                $result = new RowsOfFields($outputs);
                $result->addRendererFunction([$this, 'renderRolesCell']);
                return $result;
            } else {
                throw new InvalidArgumentException(sprintf('Could not create a new user account with the name %s.', $name));
            }
        } else {
            return new RowsOfFields([]);
        }
    }

    public function renderRolesCell($key, $cellData, FormatterOptions $options)
    {
        if (is_array($cellData)) {
            return implode("\n", $cellData);
        }
        return $cellData;
    }
}
