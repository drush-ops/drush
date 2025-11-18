<?php

declare(strict_types=1);

namespace Drush\Commands\role;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\user\Entity\Role;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Display roles and their permissions.',
    aliases: ['rls', 'role-list']
)]
#[CLI\FieldLabels(labels: ['rid' => 'ID', 'label' => 'Role Label', 'perms' => 'Permissions'])]
#[CLI\FilterDefaultField(field: 'perms')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class RoleListCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    const string NAME = 'role:list';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addUsage("role:list --filter='administer nodes'")
            ->addUsage("role:list --filter='rid=anonymous'");

        $this->setHelp('Display roles and their permissions. Use --filter to show only roles with specific permissions or specific role IDs.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $rows = [];
        $roles = Role::loadMultiple();
        foreach ($roles as $role) {
            $rows[$role->id()] = [
                'rid' => $role->id(),
                'label' => $role->label(),
                'perms' => $role->getPermissions(),
            ];
        }
        return (new RowsOfFields($rows))->addRendererFunction($this->renderPermsCell(...));
        ;
    }

    public function renderPermsCell($key, $cellData, FormatterOptions $options): string
    {
        if (is_array($cellData)) {
            return implode(',', $cellData);
        }
        return $cellData;
    }
}
