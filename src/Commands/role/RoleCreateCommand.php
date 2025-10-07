<?php

declare(strict_types=1);

namespace Drush\Commands\role;

use Drupal\user\Entity\Role;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Create a new role.',
    aliases: ['rcrt', 'role-create']
)]
final class RoleCreateCommand extends Command
{
    use AutowireTrait;

    const NAME = 'role:create';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('machine_name', InputArgument::REQUIRED, 'The symbolic machine name for the role.')
            ->addArgument('human_readable_name', InputArgument::OPTIONAL, 'A descriptive name for the role.')
            ->addUsage("role:create 'test_role' 'Test role'");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $machineName = $input->getArgument('machine_name');
        $humanReadableName = $input->getArgument('human_readable_name');

        $role = Role::create([
            'id' => $machineName,
            'label' => $humanReadableName ?: ucfirst($machineName),
        ]);
        $role->save();

        $io->success(sprintf('Created "%s', $machineName));

        return self::SUCCESS;
    }
}
