<?php

declare(strict_types=1);

namespace Drush\Commands\role;

use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\user\Entity\Role;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\cache\CacheRebuildCommand;
use Drush\Drush;
use Drush\SiteAlias\ProcessManager;
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
    description: 'Remove specified permission(s) from a role.',
    aliases: ['rmp', 'role-remove-perm']
)]
#[CLI\ValidateEntityLoad(entityType: 'user_role', argumentName: 'machine_name')]
#[CLI\ValidatePermissions(argName: 'permissions')]
final class RolePermRemoveCommand extends Command
{
    use AutowireTrait;

    const NAME = 'role:perm:remove';

    public function __construct(
        private readonly ProcessManager $processManager,
        private readonly SiteAliasManagerInterface $siteAliasManager,
        protected DateFormatterInterface $dateFormatter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('machine_name', InputArgument::REQUIRED, 'The role to modify.')
            ->addArgument('permissions', InputArgument::REQUIRED, 'The list of permission to grant, delimited by commas.')
            ->addUsage("role:remove-perm anonymous 'post comments,access content'");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $machineName = $input->getArgument('machine_name');
        $permissions = $input->getArgument('permissions');

        $perms = StringUtils::csvToArray($permissions);
        user_role_revoke_permissions($machineName, $perms);
        $io->success(sprintf('Removed "%s" permission from "%s" role', $permissions, $machineName));
        $this->processManager->drush($this->siteAliasManager->getSelf(), CacheRebuildCommand::NAME, [], Drush::redispatchOptions() + ['strict' => 0]);
        return self::SUCCESS;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('machine_name')) {
            $suggestions->suggestValues(array_keys(Role::loadMultiple()));
        }
    }
}
