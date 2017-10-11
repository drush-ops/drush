<?php
namespace Drush\Drupal\Commands\core;

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
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function information($names = '', $options = ['format' => 'table', 'uid' => self::REQ, 'mail' => self::REQ])
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
     * @aliases ublk,user-block
     * @usage drush user:block user3
     *   Block the users whose name is user3
     */
    public function block($names)
    {
        if ($names = StringUtils::csvToArray($names)) {
            foreach ($names as $name) {
                if ($account = user_load_by_name($name)) {
                    $account->block();
                    $account->save();
                    $this->logger->success(dt('Blocked user(s): !user', array('!user' => $name)));
                } else {
                    $this->logger->warning(dt('Unable to load user: !user', array('!user' => $name)));
                }
            }
        }
    }

    /**
     * UnBlock the specified user(s).
     *
     * @command user:unblock
     *
     * @param string $names A comma delimited list of user names.
     * @aliases uublk,user-unblock
     * @usage drush user:unblock user3
     *   Unblock the users with name user3
     */
    public function unblock($names)
    {
        if ($names = StringUtils::csvToArray($names)) {
            foreach ($names as $name) {
                if ($account = user_load_by_name($name)) {
                    $account->activate();
                    $account->save();
                    $this->logger->success(dt('Unblocked user(s): !user', array('!user' => $name)));
                } else {
                    $this->logger->warning(dt('Unable to load user: !user', array('!user' => $name)));
                }
            }
        }
    }

    /**
     * Add a role to the specified user accounts.
     *
     * @command user:role:add
     *
     * @validate-entity-load user_role role
     * @param string $role The name of the role to add.
     * @param string $names A comma delimited list of user names.
     * @aliases urol,user-add-role
     * @usage drush user:add-role "power user" user3
     *   Add the "power user" role to user3
     */
    public function addRole($role, $names)
    {
        if ($names = StringUtils::csvToArray($names)) {
            foreach ($names as $name) {
                if ($account = user_load_by_name($name)) {
                    $account->addRole($role);
                    $account->save();
                    $this->logger->success(dt('Added !role role to !user', array(
                    '!role' => $role,
                    '!user' => $name,
                    )));
                } else {
                    $this->logger->warning(dt('Unable to load user: !user', array('!user' => $name)));
                }
            }
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
     * @aliases urrol,user-remove-role
     * @usage drush user:remove-role "power user" user3
     *   Remove the "power user" role from user3
     */
    public function removeRole($role, $names)
    {
        if ($names = StringUtils::csvToArray($names)) {
            foreach ($names as $name) {
                if ($account = user_load_by_name($name)) {
                    $account->removeRole($role);
                    $account->save();
                    $this->logger->success(dt('Removed !role role from !user', array(
                    '!role' => $role,
                    '!user' => $name,
                    )));
                } else {
                    $this->logger->warning(dt('Unable to load user: !user', array('!user' => $name)));
                }
            }
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
    public function create($name, $options = ['password' => self::REQ, 'mail' => self::REQ])
    {
        $new_user = array(
            'name' => $name,
            'pass' => $options['password'],
            'mail' => $options['mail'],
            'access' => '0',
            'status' => 1,
        );
        if (!Drush::simulate()) {
            if ($account = User::create($new_user)) {
                $account->save();
                drush_backend_set_result($this->infoArray($account));
                $this->logger()->success(dt('Created a new user with uid !uid', array('!uid' => $account->id())));
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
    public function createValidate(CommandData $commandData)
    {
        if ($mail = $commandData->input()->getOption('mail')) {
            if (user_load_by_mail($mail)) {
                throw new \Exception(dt('There is already a user account with the email !mail', array('!mail' => $mail)));
            }
        }
        $name = $commandData->input()->getArgument('name');
        if (user_load_by_name($name)) {
            throw new \Exception((dt('There is already a user account with the name !name', array('!name' => $name))));
        }
    }

    /**
     * Cancel user account(s) with the specified name(s).
     *
     * @command user:cancel
     *
     * @param string $names A comma delimited list of user names.
     * @option delete-content Delete all content created by the user
     * @aliases ucan,user-cancel
     * @usage drush user:cancel username
     *   Cancel the user account with the name username and anonymize all content created by that user.
     * @usage drush user:cancel --delete-content username
     *   Cancel the user account with the name username and delete all content created by that user.
     */
    public function cancel($names, $options = ['delete-content' => false])
    {
        if ($names = StringUtils::csvToArray($names)) {
            foreach ($names as $name) {
                if ($account = user_load_by_name($name)) {
                    if ($options['delete-content']) {
                        $this->logger()->warning(dt('All content created by !name will be deleted.', array('!name' => $account->getUsername())));
                    }
                    if ($this->io()->confirm('Cancel user account?: ')) {
                        $method = $options['delete-content'] ? 'user_cancel_delete' : 'user_cancel_block';
                        user_cancel([], $account->id(), $method);
                        drush_backend_batch_process();
                        // Drupal logs a message for us.
                    }
                } else {
                    $this->logger()->warning(dt('Unable to load user: !user', array('!user' => $name)));
                }
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
     *   Set the password for the username someuser. @see xkcd.com/936
     */
    public function password($name, $password)
    {
        if ($account = user_load_by_name($name)) {
            if (!Drush::simulate()) {
                $account->setpassword($password);
                $account->save();
                $this->logger()->success(dt('Changed password for !name.', array('!name' => $name)));
            }
        } else {
            throw new \Exception(dt('Unable to load user: !user', array('!user' => $name)));
        }
    }

    /**
     * A flatter and simpler array presentation of a Drupal $user object.
     *
     * @param $account A user account
     * @return array
     */
    public function infoArray($account)
    {
        return array(
            'uid' => $account->id(),
            'name' => $account->getUsername(),
            'password' => $account->getPassword(),
            'mail' => $account->getEmail(),
            'user_created' => $account->getCreatedTime(),
            'created' => format_date($account->getCreatedTime()),
            'user_access' => $account->getLastAccessedTime(),
            'access' => format_date($account->getLastAccessedTime()),
            'user_login' => $account->getLastLoginTime(),
            'login' => format_date($account->getLastLoginTime()),
            'user_status' => $account->get('status')->value,
            'status' => $account->isActive() ? 'active' : 'blocked',
            'timezone' => $account->getTimeZone(),
            'roles' => $account->getRoles(),
            'langcode' => $account->getPreferredLangcode(),
            'uuid' => $account->uuid->value,
        );
    }
}
