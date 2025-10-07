<?php

declare(strict_types=1);

namespace Drush\Commands\role;

use Drupal\user\Entity\Role;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Delete a role.',
    aliases: ['rdel', 'role-delete']
)]
#[CLI\ValidateEntityLoad(entityType: 'user_role', argumentName: 'machine_name')]
final class RoleDeleteCommand extends Command
{
    use AutowireTrait;

    const NAME = 'role:delete';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('machine_name', InputArgument::REQUIRED, 'The machine name for the role.')
            ->addUsage("role:delete 'test_role'");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $machineName = $input->getArgument('machine_name');

        $role = Role::load($machineName);
        $role->delete();

        $io->success(sprintf('Deleted %s role', $machineName));

        return self::SUCCESS;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('machine_name')) {
            $suggestions->suggestValues(array_keys(Role::loadMultiple()));
        }
    }
}
