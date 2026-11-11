<?php

declare(strict_types=1);

namespace Drush\Command\user;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Set the password for the user account with the specified name.',
    aliases: ['upwd', 'user-password'],
    usages: ["user:password someuser 'correct horse battery staple'"],
)]
class UserPasswordCommand
{
    use AutowireTrait;
    use StringTranslationTrait;

    public const string NAME = 'user:password';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The name of the account to modify.')]
        string $name,
        #[Argument('The new password for the account.')]
        string $password,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $accounts = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $name]);
        $account = reset($accounts);
        if (!$account) {
            throw new \InvalidArgumentException((string) $this->t('Unable to load user: @name', ['@name' => $name]));
        }
        $account->setPassword($password);
        $account->save();
        $io->success((string) $this->t('Changed password for @name.', ['@name' => $name]));

        return Command::SUCCESS;
    }
}
