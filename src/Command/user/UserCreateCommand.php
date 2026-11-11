<?php

declare(strict_types=1);

namespace Drush\Command\user;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Create a user account.',
    aliases: ['ucrt', 'user-create'],
    usages: ["user:create newuser --mail='person@example.com' --password='letmein'"],
)]
class UserCreateCommand
{
    use AutowireTrait;
    use UserTrait;

    public const string NAME = 'user:create';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        #[Autowire('@logger.channel.user')]
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
        $io->success((string) $this->t('Created a new user with uid @uid', ['@uid' => $account->id()]));

        return Command::SUCCESS;
    }
}
