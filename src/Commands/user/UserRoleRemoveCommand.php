<?php

declare(strict_types=1);

namespace Drush\Commands\user;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\user\Entity\Role;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Remove a role from the specified user accounts.',
    aliases: ['urrol', 'user-remove-role']
)]
#[CLI\ValidateEntityLoad(entityType: 'user_role', argumentName: 'role')]
final class UserRoleRemoveCommand extends Command
{
    use AutowireTrait;
    use UserTrait;

    public const string NAME = 'user:role:remove';

    public function __construct(
        protected DateFormatterInterface $dateFormatter,
        protected readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('role', InputArgument::REQUIRED, 'The machine name of the role to remove.')
            ->addArgument('names', InputArgument::OPTIONAL, 'A comma delimited list of user names.', '')
            ->addOption('uid', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of user ids to lookup (an alternative to names).')
            ->addOption('mail', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of emails to lookup (an alternative to names).')
            ->addUsage("user:role:remove 'power_user' user3");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $role = $input->getArgument('role');
        $names = $input->getArgument('names');
        $options = [
            'uid' => $input->getOption('uid'),
            'mail' => $input->getOption('mail'),
        ];

        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            $account->removeRole($role);
            $account->save();
            $io->success(sprintf('Removed %s role from %s', $role, $account->getAccountName()));
        }

        return self::SUCCESS;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('role')) {
            $suggestions->suggestValues(array_keys(Role::loadMultiple()));
        }
    }
}
