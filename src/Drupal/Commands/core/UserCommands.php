<?php

namespace Drush\Drupal\Commands\core;

use Drupal\Core\Datetime\DateFormatterInterface;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Utils\StringUtils;

class UserCommands extends DrushCommands
{
    /**
     * @var DateFormatterInterface
     */
    protected $dateFormatter;

    public function __construct($dateFormatter)
    {
        $this->dateFormatter = $dateFormatter;
    }


    /**
     * Print information about the specified user(s).
     *
     * @command user:information
     *
     * @param string $names A comma delimited list of user names.
     * @option $uid A comma delimited list of user ids to lookup (an alternative to names).
     * @option $mail A comma delimited list of emails to lookup (an alternative to names).
     * @aliases uinf,user-information
     * @usage drush user:information someguy,somegal
     *   Display information about the someguy and somegal user accounts.
     * @usage drush user:information --mail=someguy@somegal.com
     *   Display information for a given email account.
     * @usage drush user:information --uid=5
     *   Display information for a given user id.
     * @usage drush uinf --uid=$(drush sqlq "SELECT GROUP_CONCAT(entity_id) FROM user__roles WHERE roles_target_id = 'administrator'")
     *   Display information for all administrators.
     * @field-labels
     *   uid: User ID
     *   name: User name
     *   pass: Password
     *   mail: User mail
     *   theme: User theme
     *   signature: Signature
     *   signature_format: Signature format
     *   user_created: User created
     *   created: Created
     *   user_access: User last access
     *   access: Last access
     *   user_login: User last login
     *   login: Last login
     *   user_status: User status
     *   status: Status
     *   timezone: Time zone
     *   picture: User picture
     *   init: Initial user mail
     *   roles: User roles
     *   group_audience: Group Audience
     *   langcode: Language code
     *   uuid: Uuid
     * @table-style default
     * @default-fields uid,name,mail,roles,user_status
     *
     * @filter-default-field name
     */
    public function information(string $names = '', $options = ['format' => 'table', 'uid' => self::REQ, 'mail' => self::REQ]): RowsOfFields
    {
        $accounts = [];
        if ($mails = StringUtils::csvToArray($options['mail'])) {
            foreach ($mails as $mail) {
                if ($account = user_load_by_mail($mail)) {
                    $accounts[$account->id()] = $account;
                }
            }
        }
        if ($uids = StringUtils::csvToArray($options['uid'])) {
            if ($loaded = User::loadMultiple($uids)) {
                $accounts += $loaded;
            }
        }
        if ($names = StringUtils::csvToArray($names)) {
            foreach ($names as $name) {
                if ($account = user_load_by_name($name)) {
                    $accounts[$account->id()] = $account;
                }
            }
        }
        if (empty($accounts)) {
            throw new \Exception(dt('Unable to find a matching user'));
        }

        foreach ($accounts as $id => $account) {
            $outputs[$id] = $this->infoArray($account);
        }

        $result = new RowsOfFields($outputs);
        $result->addRendererFunction([$this, 'renderRolesCell']);
        return $result;
    }

    public function renderRolesCell($key, $cellData, FormatterOptions $options)
    {
        if (is_array($cellData)) {
            return implode("\n", $cellData);
        }
        return $cellData;
    }

    /**
     * Block the specified user(s).
     *
     * @command user:block
     *
     * @param string $names A comma delimited list of user names.
     * @option $uid A comma delimited list of user ids to lookup (an alternative to names).
     * @option $mail A comma delimited list of emails to lookup (an alternative to names).
     * @aliases ublk,user-block
     * @usage drush user:block user3
     *   Block the users whose name is user3
     */
    public function block(string $names = '', $options = ['uid' => self::REQ, 'mail' => self::REQ]): void
    {
        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            $account->block();
            $account->save();
            $this->logger->success(dt('Blocked user(s): !user', ['!user' => $account->getAccountName()]));
        }
    }

    /**
     * Unblock the specified user(s).
     *
     * @command user:unblock
     *
     * @param string $names A comma delimited list of user names.
     * @option $uid A comma delimited list of user ids to lookup (an alternative to names).
     * @option $mail A comma delimited list of emails to lookup (an alternative to names).
     * @aliases uublk,user-unblock
     * @usage drush user:unblock user3
     *   Unblock the users with name user3
     */
    public function unblock(string $names = '', $options = ['uid' => self::REQ, 'mail' => self::REQ]): void
    {
        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            $account->activate();
            $account->save();
            $this->logger->success(dt('Unblocked user(s): !user', ['!user' => $account->getAccountName()]));
        }
    }

    /**
     * Add a role to the specified user accounts.
     *
     * @command user:role:add
     *
     * @validate-entity-load user_role role
     * @param string $role The machine name of the role to add.
     * @param string $names A comma delimited list of user names.
     * @option $uid A comma delimited list of user ids to lookup (an alternative to names).
     * @option $mail A comma delimited list of emails to lookup (an alternative to names).
     * @aliases urol,user-add-role
     * @usage drush user-add-role "editor" user3
     *   Add the editor role to user3
     */
    public function addRole(string $role, string $names = '', $options = ['uid' => self::REQ, 'mail' => self::REQ]): void
    {
        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            $account->addRole($role);
            $account->save();
            $this->logger->success(dt('Added !role role to !user', [
            '!role' => $role,
            '!user' => $account->getAccountName(),
            ]));
        }
    }

    /**
     * Remove a role from the specified user accounts.
     *
     * @command user:role:remove
     *
     * @validate-entity-load user_role role
     * @param string $role The name of the role to add
     * @param string $names A comma delimited list of user names.
     * @option $uid A comma delimited list of user ids to lookup (an alternative to names).
     * @option $mail A comma delimited list of emails to lookup (an alternative to names).
     * @aliases urrol,user-remove-role
     * @usage drush user:remove-role "power user" user3
     *   Remove the "power user" role from user3
     */
    public function removeRole(string $role, string $names = '', $options = ['uid' => self::REQ, 'mail' => self::REQ]): void
    {
        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            $account->removeRole($role);
            $account->save();
            $this->logger->success(dt('Removed !role role from !user', [
            '!role' => $role,
            '!user' => $account->getAccountName(),
            ]));
        }
    }

    /**
     * Create a user account.
     *
     * @command user:create
     *
     * @param string $name The name of the account to add
     * @option password The password for the new account
     * @option mail The email address for the new account
     * @aliases ucrt,user-create
     * @usage drush user:create newuser --mail="person@example.com" --password="letmein"
     *   Create a new user account with the name newuser, the email address person@example.com, and the password letmein
     */
    public function create(string $name, $options = ['password' => self::REQ, 'mail' => self::REQ])
    {
        $new_user = [
            'name' => $name,
            'pass' => $options['password'],
            'mail' => $options['mail'],
            'access' => '0',
            'status' => 1,
        ];
        if (!$this->getConfig()->simulate()) {
            if ($account = User::create($new_user)) {
                $account->save();
                $this->logger()->success(dt('Created a new user with uid !uid', ['!uid' => $account->id()]));
            } else {
                return new CommandError("Could not create a new user account with the name " . $name . ".");
            }
        }
    }

    /**
     * Assure that provided username is available.
     *
     * @hook validate user-create
     */
    public function createValidate(CommandData $commandData): void
    {
        if ($mail = $commandData->input()->getOption('mail')) {
            if (user_load_by_mail($mail)) {
                throw new \Exception(dt('There is already a user account with the email !mail', ['!mail' => $mail]));
            }
        }
        $name = $commandData->input()->getArgument('name');
        if (user_load_by_name($name)) {
            throw new \Exception((dt('There is already a user account with the name !name', ['!name' => $name])));
        }
    }

    /**
     * Cancel user account(s) with the specified name(s).
     *
     * @command user:cancel
     *
     * @param string $names A comma delimited list of user names.
     * @option delete-content Delete the user, and all content created by the user
     * @option $uid A comma delimited list of user ids to lookup (an alternative to names).
     * @option $mail A comma delimited list of emails to lookup (an alternative to names).
     * @aliases ucan,user-cancel
     * @usage drush user:cancel username
     *   Cancel the user account with the name username and anonymize all content created by that user.
     * @usage drush user:cancel --delete-content username
     *   Delete the user account with the name username and delete all content created by that user.
     */
    public function cancel(string $names, $options = ['delete-content' => false, 'uid' => self::REQ, 'mail' => self::REQ]): void
    {
        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            if ($options['delete-content']) {
                $this->logger()->warning(dt('All content created by !name will be deleted.', ['!name' => $account->getAccountName()]));
            }
            if ($this->io()->confirm('Cancel user account?: ')) {
                $method = $options['delete-content'] ? 'user_cancel_delete' : 'user_cancel_block';
                user_cancel([], $account->id(), $method);
                drush_backend_batch_process();
                // Drupal logs a message for us.
            }
        }
    }

    /**
     * Set the password for the user account with the specified name.
     *
     * @command user:password
     *
     * @param string $name The name of the account to modify.
     * @param string $password The new password for the account.
     * @aliases upwd,user-password
     * @usage drush user:password someuser "correct horse battery staple"
     *   Set the password for the username someuser. See https://xkcd.com/936
     */
    public function password(string $name, string $password): void
    {
        if ($account = user_load_by_name($name)) {
            if (!$this->getConfig()->simulate()) {
                $account->setpassword($password);
                $account->save();
                $this->logger()->success(dt('Changed password for !name.', ['!name' => $name]));
            }
        } else {
            throw new \Exception(dt('Unable to load user: !user', ['!user' => $name]));
        }
    }

    /**
     * A flatter and simpler array presentation of a Drupal $user object.
     *
     * @param $account A user account
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

    /**
     * Get accounts from name variables or uid & mail options.
     *
     * @param string $names
     * @param array $options
     *
     *   A array of loaded accounts.
     * @throws \Exception
     */
    protected function getAccounts(string $names = '', array $options = []): array
    {
        $accounts = [];
        if ($mails = StringUtils::csvToArray($options['mail'])) {
            foreach ($mails as $mail) {
                if ($account = user_load_by_mail($mail)) {
                    $accounts[$account->id()] = $account;
                } else {
                    $this->logger->warning(dt('Unable to load user: !mail', ['!mail' => $mail]));
                }
            }
        }
        if ($uids = StringUtils::csvToArray($options['uid'])) {
            foreach ($uids as $uid) {
                if ($account = User::load($uid)) {
                    $accounts[$account->id()] = $account;
                } else {
                    $this->logger->warning(dt('Unable to load user: !uid', ['!uid' => $uid]));
                }
            }
        }
        if ($names = StringUtils::csvToArray($names)) {
            foreach ($names as $name) {
                if ($account = user_load_by_name($name)) {
                    $accounts[$account->id()] = $account;
                } else {
                    $this->logger->warning(dt('Unable to load user: !user', ['!user' => $name]));
                }
            }
        }
        if (empty($accounts)) {
            throw new \Exception(dt('Unable to find any matching user'));
        }

        return  $accounts;
    }
}
