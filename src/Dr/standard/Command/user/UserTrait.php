<?php

declare(strict_types=1);

namespace Drush\Dr\standard\Command\user;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Drush\Utils\StringUtils;

/**
 * Helpers shared by the user commands.
 *
 * Classes using this trait inject EntityTypeManagerInterface as
 * $this->entityTypeManager and LoggerInterface as $this->logger.
 */
trait UserTrait
{
    use StringTranslationTrait;

    /**
     * Loads the accounts selected by a names argument and the --uid and
     * --mail options, each a comma delimited list.
     *
     * Logs a warning for every value that matches no account and throws when
     * none of them did.
     *
     * @return UserInterface[]
     *   The accounts, keyed by uid.
     */
    protected function getAccounts(string $names, ?string $uid = null, ?string $mail = null): array
    {
        $storage = $this->entityTypeManager->getStorage('user');
        $accounts = [];
        foreach (StringUtils::csvToArray($mail) as $value) {
            $matches = $storage->loadByProperties(['mail' => $value]);
            if ($account = reset($matches)) {
                $accounts[$account->id()] = $account;
            } else {
                $this->logger?->warning((string) $this->t('Unable to load user: @mail', ['@mail' => $value]));
            }
        }
        foreach (StringUtils::csvToArray($uid) as $value) {
            if ($account = $storage->load($value)) {
                $accounts[$account->id()] = $account;
            } else {
                $this->logger?->warning((string) $this->t('Unable to load user: @uid', ['@uid' => $value]));
            }
        }
        foreach (StringUtils::csvToArray($names) as $value) {
            $matches = $storage->loadByProperties(['name' => $value]);
            if ($account = reset($matches)) {
                $accounts[$account->id()] = $account;
            } else {
                $this->logger?->warning((string) $this->t('Unable to load user: @user', ['@user' => $value]));
            }
        }
        if ($accounts === []) {
            throw new \InvalidArgumentException((string) $this->t('Unable to find any matching user'));
        }

        return $accounts;
    }
}
