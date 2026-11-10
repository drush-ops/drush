<?php

declare(strict_types=1);

namespace Drush\Commands\role;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\RoleInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Display roles and their permissions.',
    aliases: ['rls', 'role-list'],
    usages: [
        "role:list --filter='administer nodes'",
        'role:list --format=json',
    ],
)]
class RoleListCommand
{
    use AutowireTrait;
    use RoleTrait;

    const string NAME = 'role:list';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Option('Show only roles that hold this permission.')]
        ?string $filter = null,
        #[Option('An alternative output format such as json or yaml. Defaults to table.')]
        ?OutputFormat $format = null,
    ): int {
        $rows = [];
        /** @var RoleInterface[] $roles */
        $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
        foreach ($roles as $role) {
            if ($filter !== null && !$role->hasPermission($filter)) {
                continue;
            }
            $rows[$role->id()] = [
                'rid' => $role->id(),
                'label' => $role->label(),
                'perms' => $role->getPermissions(),
            ];
        }

        $result = match ($format) {
            OutputFormat::Json => json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => Yaml::encode($rows),
            default => $this->renderTable($rows),
        };
        $output->writeln($result, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    protected function renderTable(array $rows): string
    {
        foreach ($rows as &$row) {
            // One permission per line keeps the table narrow.
            $row['perms'] = implode("\n", $row['perms']);
        }
        $buffer = new BufferedOutput();
        $table = new Table($buffer);
        $table
            ->setHeaders(['ID', 'Role Label', 'Permissions'])
            ->setRows($rows)
            ->render();
        return $buffer->fetch();
    }
}
