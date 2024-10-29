<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

final class UserCommands extends DrushCommands
{
    use AutowireTrait;

    const INFORMATION = 'user:information';
    const BLOCK = 'user:block';
    const UNBLOCK = 'user:unblock';
    const ROLE_ADD = 'user:role:add';
    const ROLE_REMOVE = 'user:role:remove';
    const CREATE = 'user:create';
    const CANCEL = 'user:cancel';
    const PASSWORD = 'user:password';
    const INF_LABELS = [
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
    const INF_DEFAULT_FIELDS = ['uid', 'name', 'mail', 'roles', 'user_status'];

    public function __construct(protected DateFormatterInterface $dateFormatter)
    {
    }

    /**
     * Print information about the specified user(s).
     */
    #[CLI\Command(name: self::INFORMATION, aliases: ['uinf', 'user-information'])]
    #[CLI\Argument(name: 'names', description: 'A comma delimited list of user names.')]
    #[CLI\Option(name: 'uid', description: 'A comma delimited list of user ids to lookup (an alternative to names).')]
    #[CLI\Option(name: 'mail', description: 'A comma delimited list of emails to lookup (an alternative to names).')]
    #[CLI\Usage(name: 'drush user:information someguy,somegal', description: 'Display information about the someguy and somegal user accounts.')]
    #[CLI\Usage(name: 'drush user:information --mail=someguy@somegal.com', description: 'Display information for a given email account.')]
    #[CLI\Usage(name: 'drush user:information --uid=5', description: 'Display information for a given user id.')]
    #[CLI\Usage(name: 'drush uinf --uid=$(drush sqlq "SELECT GROUP_CONCAT(entity_id) FROM user__roles WHERE roles_target_id = \'administrator\'")', description: 'Display information for all administrators.')]
    #[CLI\FieldLabels(labels: self::INF_LABELS)]
    #[CLI\DefaultTableFields(fields: self::INF_DEFAULT_FIELDS)]
    #[CLI\FilterDefaultField(field: 'name')]
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
        if ($accounts === []) {
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
     */
    #[CLI\Command(name: self::BLOCK, aliases: ['ublk', 'user-block'])]
    #[CLI\Argument(name: 'names', description: 'A comma delimited list of user names.')]
    #[CLI\Option(name: 'uid', description: 'A comma delimited list of user ids to lookup (an alternative to names).')]
    #[CLI\Option(name: 'mail', description: 'A comma delimited list of emails to lookup (an alternative to names).')]
    #[CLI\Usage(name: 'drush user:block user3', description: 'Block the user whose name is <info>user3</info>')]
    #[CLI\Usage(name: 'drush user:cancel user3 --delete-content', description: '<info>Delete</info> the user whose name is <info>user3</info> and delete her content.')]
    #[CLI\Usage(name: 'drush user:cancel user3 --reassign-content', description: '<info>Delete</info> the user whose name is <info>user3</info> and reassign her content to the anonymous user.')]
    public function block(string $names = '', $options = ['uid' => self::REQ, 'mail' => self::REQ]): void
    {
        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            $account->block();
            $account->save();
            $this->logger()->success(dt('Blocked user(s): !user', ['!user' => $account->getAccountName()]));
        }
    }

    /**
     * Unblock the specified user(s).
     */
    #[CLI\Command(name: self::UNBLOCK, aliases: ['uublk', 'user-unblock'])]
    #[CLI\Argument(name: 'names', description: 'A comma delimited list of user names.')]
    #[CLI\Option(name: 'uid', description: 'A comma delimited list of user ids to lookup (an alternative to names).')]
    #[CLI\Option(name: 'mail', description: 'A comma delimited list of emails to lookup (an alternative to names).')]
    #[CLI\Usage(name: 'drush user:unblock user3', description: 'Unblock the user whose name is <info>user3</info>')]
    public function unblock(string $names = '', $options = ['uid' => self::REQ, 'mail' => self::REQ]): void
    {
        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            $account->activate();
            $account->save();
            $this->logger()->success(dt('Unblocked user(s): !user', ['!user' => $account->getAccountName()]));
        }
    }

    /**
     * Add a role to the specified user accounts.
     */
    #[CLI\Command(name: self::ROLE_ADD, aliases: ['urol', 'user-add-role'])]
    #[CLI\Argument(name: 'role', description: 'The machine name of the role to add.')]
    #[CLI\Argument(name: 'names', description: 'A comma delimited list of user names.')]
    #[CLI\Option(name: 'uid', description: 'A comma delimited list of user ids to lookup (an alternative to names).')]
    #[CLI\Option(name: 'mail', description: 'A comma delimited list of emails to lookup (an alternative to names).')]
    #[CLI\Usage(name: 'drush user:role:add \'editor\' user3', description: 'Add the editor role to user3')]
    #[CLI\ValidateEntityLoad(entityType: 'user_role', argumentName: 'role')]
    #[CLI\Complete(method_name_or_callable: 'roleComplete')]
    public function addRole(string $role, string $names = '', $options = ['uid' => self::REQ, 'mail' => self::REQ]): void
    {
        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            $account->addRole($role);
            $account->save();
            $this->logger()->success(dt('Added !role role to !user', [
            '!role' => $role,
            '!user' => $account->getAccountName(),
            ]));
        }
    }

    /**
     * Remove a role from the specified user accounts.
     */
    #[CLI\Command(name: self::ROLE_REMOVE, aliases: ['urrol', 'user-remove-role'])]
    #[CLI\Argument(name: 'role', description: 'The machine name of the role to remove.')]
    #[CLI\Argument(name: 'names', description: 'A comma delimited list of user names.')]
    #[CLI\Option(name: 'uid', description: 'A comma delimited list of user ids to lookup (an alternative to names).')]
    #[CLI\Option(name: 'mail', description: 'A comma delimited list of emails to lookup (an alternative to names).')]
    #[CLI\Usage(name: "drush user:role:remove 'power_user' user3", description: "Remove the power_user role from user3")]
    #[CLI\ValidateEntityLoad(entityType: 'user_role', argumentName: 'role')]
    #[CLI\Complete(method_name_or_callable: 'roleComplete')]
    public function removeRole(string $role, string $names = '', $options = ['uid' => self::REQ, 'mail' => self::REQ]): void
    {
        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            $account->removeRole($role);
            $account->save();
            $this->logger()->success(dt('Removed !role role from !user', [
            '!role' => $role,
            '!user' => $account->getAccountName(),
            ]));
        }
    }

    /**
     * Create a user account.
     */
    #[CLI\Command(name: self::CREATE, aliases: ['ucrt', 'user-create'])]
    #[CLI\Argument(name: 'name', description: 'The name of the account to add')]
    #[CLI\Option(name: 'password', description: 'The password for the new account')]
    #[CLI\Option(name: 'mail', description: 'The email address for the new account')]
    #[CLI\FieldLabels(labels: self::INF_LABELS)]
    #[CLI\DefaultTableFields(fields: self::INF_DEFAULT_FIELDS)]
    #[CLI\FilterDefaultField(field: 'name')]
    #[CLI\Usage(name: "drush user:create newuser --mail='person@example.com' --password='letmein'", description: 'Create a new user account with the name newuser, the email address person@example.com, and the password letmein')]
    public function createUser(string $name, $options = ['format' => 'table', 'password' => self::REQ, 'mail' => self::REQ]): RowsOfFields|CommandError
    {
        $new_user = [
            'name' => $name,
            'pass' => $options['password'],
            'mail' => $options['mail'],
            'access' => '0',
            'status' => 1,
        ];
        if (!$this->getConfig()->simulate()) {
            // @phpstan-ignore if.alwaysTrue
            if ($account = User::create($new_user)) {
                $account->save();
                $this->logger()->success(dt('Created a new user with uid !uid', ['!uid' => $account->id()]));
                $outputs[$account->id()] = $this->infoArray($account);

                $result = new RowsOfFields($outputs);
                $result->addRendererFunction([$this, 'renderRolesCell']);
                return $result;
            } else {
                return new CommandError("Could not create a new user account with the name " . $name . ".");
            }
        } else {
            return new RowsOfFields([]);
        }
    }

    /**
     * Assure that provided username is available.
     */
    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: self::CREATE)]
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
     * Block or delete user account(s) with the specified name(s).
     *
     * - Existing content may be deleted or reassigned to the Anonymous user. See options.
     * - By default only nodes are deleted or reassigned. Custom entity types need own code to
     * support cancellation. See https://www.drupal.org/project/drupal/issues/3043725 for updates.
     */
    #[CLI\Command(name: self::CANCEL, aliases: ['ucan', 'user-cancel'])]
    #[CLI\Argument(name: 'names', description: 'A comma delimited list of user names.')]
    #[CLI\Option(name: 'uid', description: 'A comma delimited list of user ids to lookup (an alternative to names).')]
    #[CLI\Option(name: 'mail', description: 'A comma delimited list of emails to lookup (an alternative to names).')]
    #[CLI\Option(name: 'reassign-content', description: 'Delete the user and make its content belong to the anonymous user.')]
    #[CLI\Option(name: 'delete-content', description: 'Delete the user, and delete all content created by that user.')]
    #[CLI\Usage(name: 'drush user:cancel username', description: 'Block the user account with the name username.')]
    #[CLI\Usage(name: 'drush user:cancel --delete-content username', description: 'Delete the user account with the name <info>username<info> and delete all content created by that user.')]
    #[CLI\Usage(name: 'drush user:cancel --reassign-content username', description: 'Delete the user account with the name <info>username<info> and assign all her content to the anonymous user.')]
    public function cancel(string $names = '', $options = ['delete-content' => false, 'reassign-content' => false, 'uid' => self::REQ, 'mail' => self::REQ]): void
    {
        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            if ($options['delete-content']) {
                $this->logger()->warning(dt('All content created by !name will be deleted.', ['!name' => $account->getAccountName()]));
            } elseif ($options['reassign-content']) {
                $this->logger()->warning(dt('All content created by !name will be assigned to anonymous user.', ['!name' => $account->getAccountName()]));
            }
            if ($this->io()->confirm('Cancel user account?: ')) {
                $method = $options['delete-content'] ? 'user_cancel_delete' : ($options['reassign-content'] ? 'user_cancel_reassign' : 'user_cancel_block');
                user_cancel([], $account->id(), $method);
                drush_backend_batch_process();
                // Drupal logs a message for us.
            }
        }
    }

    /**
     * Set the password for the user account with the specified name.
     */
    #[CLI\Command(name: self::PASSWORD, aliases: ['upwd', 'user-password'])]
    #[CLI\Argument(name: 'name', description: 'The name of the account to modify.')]
    #[CLI\Argument(name: 'password', description: 'The new password for the account.')]
    #[CLI\Usage(name: "drush user:password someuser 'correct horse battery staple'", description: 'Set the password for the username someuser. See https://xkcd.com/936')]
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
     * @param array $options
     *
     *   A array of loaded accounts.
     * @throws \Exception
     */
    protected function getAccounts(string $names = '', array $options = []): array
    {
        $accounts = [];
        if (isset($options['mail']) && $mails = StringUtils::csvToArray($options['mail'])) {
            foreach ($mails as $mail) {
                if ($account = user_load_by_mail($mail)) {
                    $accounts[$account->id()] = $account;
                } else {
                    $this->logger->warning(dt('Unable to load user: !mail', ['!mail' => $mail]));
                }
            }
        }
        if (isset($options['uid']) && $uids = StringUtils::csvToArray($options['uid'])) {
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
        if ($accounts === []) {
            throw new \Exception(dt('Unable to find any matching user'));
        }

        return  $accounts;
    }

    public function roleComplete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('role')) {
            $suggestions->suggestValues(array_keys(Role::loadMultiple()));
        }
    }
}
