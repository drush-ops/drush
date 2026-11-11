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
use Symfony\Component\Console\Output\OutputInterface;

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

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected DateFormatterInterface $dateFormatter,
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
        $accounts = $this->getAccounts($names, $uid, $mail);
        $this->writeAccounts($output, $accounts, $this->dateFormatter, $format, $fields);

        return Command::SUCCESS;
    }
}
