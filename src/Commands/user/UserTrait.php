<?php

namespace Drush\Commands\user;

use Drupal\user\Entity\User;
use Drush\Utils\StringUtils;

trait UserTrait
{
    const array INF_LABELS = [
        'uid' => 'User ID',
        'name' => 'User name',
        'pass' => 'Password',
        'mail' => 'User mail',
        'theme' => 'User theme',
        'signature' => 'Signature',
        'signature_format' => 'Signature format',
        'user_created' => 'User created',
        'created' => 'Created',
        'user_access' => 'User last access',
        'access' => 'Last access',
        'user_login' => 'User last login',
        'login' => 'Last login',
        'user_status' => 'User status',
        'status' => 'Status',
        'timezone' => 'Time zone',
        'picture' => 'User picture',
        'init' => 'Initial user mail',
        'roles' => 'User roles',
        'group_audience' => 'Group Audience',
        'langcode' => 'Language code',
        'uuid' => 'Uuid',
    ];

   const array INF_DEFAULT_FIELDS = ['uid', 'name', 'mail', 'roles', 'user_status'];

    protected function getAccounts(string $names = '', array $options = []): array
    {
        $accounts = [];
        if (isset($options['mail']) && $mails = StringUtils::csvToArray($options['mail'])) {
            foreach ($mails as $mail) {
                if ($account = user_load_by_mail($mail)) {
                    $accounts[$account->id()] = $account;
                } else {
                    $this->logger->warning('Unable to load user: {mail}', ['mail' => $mail]);
                }
            }
        }
        if (isset($options['uid']) && $uids = StringUtils::csvToArray($options['uid'])) {
            foreach ($uids as $uid) {
                if ($account = User::load($uid)) {
                    $accounts[$account->id()] = $account;
                } else {
                    $this->logger->warning('Unable to load user: {uid}', ['uid' => $uid]);
                }
            }
        }
        if ($names = StringUtils::csvToArray($names)) {
            foreach ($names as $name) {
                if ($account = user_load_by_name($name)) {
                    $accounts[$account->id()] = $account;
                } else {
                    $this->logger->warning('Unable to load user: {user}', ['user' => $name]);
                }
            }
        }
        if ($accounts === []) {
            throw new \Exception('Unable to find any matching user');
        }

        return  $accounts;
    }

    /**
     * A flatter and simpler array presentation of a Drupal $user object.
     */
    public function infoArray($account): array
    {
        return [
            'uid' => $account->id(),
            'name' => $account->getAccountName(),
            'pass' => $account->getPassword(),
            'mail' => $account->getEmail(),
            'user_created' => $account->getCreatedTime(),
            'created' => $this->dateFormatter->format($account->getCreatedTime()),
            'user_access' => $account->getLastAccessedTime(),
            'access' => $this->dateFormatter->format($account->getLastAccessedTime()),
            'user_login' => $account->getLastLoginTime(),
            'login' => $this->dateFormatter->format($account->getLastLoginTime()),
            'user_status' => $account->get('status')->value,
            'status' => $account->isActive() ? 'active' : 'blocked',
            'timezone' => $account->getTimeZone(),
            'roles' => $account->getRoles(),
            'langcode' => $account->getPreferredLangcode(),
            'uuid' => $account->uuid->value,
        ];
    }
}
