<?php

declare(strict_types=1);

namespace Drush\Commands\role;

use Drupal\user\Entity\Role;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Grant specified permission(s) to a role.',
    aliases: ['rap', 'role-add-perm']
)]
#[CLI\ValidateEntityLoad(entityType: 'user_role', argumentName: 'machine_name')]
#[CLI\ValidatePermissions(argName: 'permissions')]
final class RolePermAddCommand extends Command
{
    use AutowireTrait;

    const string NAME = 'role:perm:add';

    protected function configure(): void
    {
        $this
            ->addArgument('machine_name', InputArgument::REQUIRED, 'The role to modify.')
            ->addArgument('permissions', InputArgument::REQUIRED, 'The list of permission to grant, delimited by commas.')
            ->addUsage("role:perm:add anonymous 'post comments'")
            ->addUsage("role:perm:add anonymous 'post comments,access content'");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $machineName = $input->getArgument('machine_name');
        $permissions = $input->getArgument('permissions');

        $perms = StringUtils::csvToArray($permissions);
        user_role_grant_permissions($machineName, $perms);

        $io->success(sprintf('Added "%s" permission to "%s" role', $permissions, $machineName));

        drupal_flush_all_caches();

        return self::SUCCESS;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('machine_name')) {
            $suggestions->suggestValues(array_keys(Role::loadMultiple()));
        }
    }
}
