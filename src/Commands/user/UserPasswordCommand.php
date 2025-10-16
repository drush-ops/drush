<?php

declare(strict_types=1);

namespace Drush\Commands\user;

use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Set the password for the user account with the specified name.',
    aliases: ['upwd', 'user-password']
)]
final class UserPasswordCommand extends Command
{
    use AutowireTrait;

    public const string NAME = 'user:password';

    public function __construct(
        protected readonly DrushConfig $drushConfig
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the account to modify.')
            ->addArgument('password', InputArgument::REQUIRED, 'The new password for the account.')
            ->addUsage("user:password someuser 'correct horse battery staple'");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $name = $input->getArgument('name');
        $password = $input->getArgument('password');

        if ($account = user_load_by_name($name)) {
            if (!$this->drushConfig->simulate()) {
                $account->setpassword($password);
                $account->save();
                $io->success(sprintf('Changed password for %s.', $name));
            }
        } else {
            throw new \Exception(sprintf('Unable to load user: %s', $name));
        }

        return self::SUCCESS;
    }
}
