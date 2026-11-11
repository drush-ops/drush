<?php

declare(strict_types=1);

namespace Drush\Commands\user;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OutputFormat;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Create a user account.',
    aliases: ['ucrt', 'user-create'],
    usages: [
        "user:create newuser --mail='person@example.com' --password='letmein'",
        'user:create anotheruser --fields=uuid,langcode',
    ],
)]
class UserCreateCommand
{
    use AutowireTrait;
    use UserTrait;

    public const string NAME = 'user:create';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected DateFormatterInterface $dateFormatter,
        protected LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The name of the account to add')]
        string $name,
        #[Option('The password for the new account')]
        ?string $password = null,
        #[Option('The email address for the new account')]
        ?string $mail = null,
        #[Option('A comma delimited list of fields to show. Use * for all. Defaults to uid,name,mail,roles,user_status for the table and to all fields for json and yaml.')]
        ?string $fields = null,
        #[Option('An alternative output format such as json or yaml. Defaults to table.')]
        ?OutputFormat $format = null,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $storage = $this->entityTypeManager->getStorage('user');
        if ($mail && $storage->loadByProperties(['mail' => $mail])) {
            throw new \InvalidArgumentException((string) $this->t('There is already a user account with the email @mail', ['@mail' => $mail]));
        }
        if ($storage->loadByProperties(['name' => $name])) {
            throw new \InvalidArgumentException((string) $this->t('There is already a user account with the name @name', ['@name' => $name]));
        }

        $account = $storage->create([
            'name' => $name,
            'pass' => $password,
            'mail' => $mail,
            'access' => '0',
            'status' => 1,
        ]);
        $account->save();

        // The confirmation goes to stderr so that json and yaml stay parseable.
        $io->getErrorStyle()->success((string) $this->t('Created a new user with uid @uid', ['@uid' => $account->id()]));
        $this->writeAccounts($output, [$account->id() => $account], $this->dateFormatter, $format, $fields);

        return Command::SUCCESS;
    }
}
