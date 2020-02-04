<?php

namespace Drush\Drupal\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Drush\Utils\StringUtils;

class SanitizeCommands extends DrushCommands implements CustomEventAwareInterface
{
    protected $entityTypeManager;

    use CustomEventAwareTrait;

    public function __construct($entityTypeManager)
    {
        $this->userStorage = $entityTypeManager->getStorage('user');
    }

    /**
     * Sanitize the database by removing or obfuscating user data.
     *
     * Commandfiles may add custom operations by implementing:
     * - @hook_on-event sql-sanitize-message
     *     Display summary to user before confirmation.
     * - @hook post-command sql-sanitize
     *     Run queries or call APIs to perform sanitizing
     *
     * @command sql:sanitize
     * @aliases sqlsan,sql-sanitize
     * @usage drush sql:sanitize --sanitize-password=no
     *   Sanitize database without modifying any passwords.
     * @usage drush sql:sanitize --whitelist-fields=field_biography,field_phone_number
     *   Sanitizes database but exempts two user fields from modification.
     * @usage drush sql:sanitize --whitelist-uids=4,5
     *   Sanitizes database but exempts two user accounts from modification based on their uids.
     * @usage drush sql:sanitize --whitelist-mails=user1@example.org, *@mycompany.org
     *   Sanitizes database but exempts user accounts from modification based on account mail.
     *   You can use * as a wildcard to target every mail account on domain.
     */
    public function sanitize()
    {
     /**
     * In order to present only one prompt, collect all confirmations from
     * commandfiles up front. sql-sanitize plugins are commandfiles that implement
     * \Drush\Commands\sql\SanitizePluginInterface
     */
        $messages = [];
        $input = $this->input();
        $handlers = $this->getCustomEventHandlers('sql-sanitize-confirms');
        foreach ($handlers as $handler) {
            $handler($messages, $input);
        }
        if (!empty($messages)) {
            $this->output()->writeln(dt('The following operations will be performed:'));
            $this->io()->listing($messages);
        }
        if (!$this->io()->confirm(dt('Do you want to sanitize the current database?'))) {
            throw new UserAbortException();
        }

        // All sanitize operations defined in post-command hooks, including Drush
        // core sanitize routines. See \Drush\Commands\sql\SanitizePluginInterface.
    }

    /**
     * @hook option sql-sanitize
     * @option whitelist-uids
     *   A comma delimited list of uids corresponding to the user accounts exempt from
     *   sanitization.
     * @option whitelist-mails
     *   A comma delimited list of mails corresponding to the user accounts exempt from sanitization.
     *   Wildcard can be used to target all mail accounts on a domain.
     */
    public function options($options = ['whitelist-uids' => '', 'whitelist-mails' => ''])
    {
    }

    /**
     * Handles wildcard mail addresses and conversion of user mails to uids.
     *
     * @hook pre-command sql-sanitize
     */
    public function handleMailWhitelist(CommandData $commandData)
    {
        $input = $commandData->input();
        $whitelist_mails = StringUtils::csvToArray($input->getOption('whitelist-mails'));
        $whitelist_uids = StringUtils::csvToArray($input->getOption('whitelist-uids'));
        $uids_mail_list = $this->uidsByMails($this->handleMailWildcard($whitelist_mails));
        $input->setOption('whitelist-uids', implode(",", array_merge($whitelist_uids, $uids_mail_list)));
    }

    /**
     * Helper function which returns user ids based on their mail addresses.
     *
     * @param array $mail_list
     *
     * @return array
     */
    private function uidsByMails($mail_list)
    {
        if (empty($mail_list)) {
            return [];
        }

        $query = $this->userStorage->getQuery();
        $query->condition('mail', $mail_list, 'IN');
        return array_values($query->execute());
    }

    /**
     * Helper function which returns a list of all mails based on wildcard.
     *
     * @param array $mail_list
     *
     * @return mixed
     */
    private function handleMailWildcard($mail_list)
    {
        foreach ($mail_list as $key => $mail) {
            $mail_parts = explode('@', $mail);
            if ($mail_parts[0]==='*') {
                $result = [];
                $query = $this->userStorage->getQuery();
                $query->condition('mail', '@' . $mail_parts[1], 'ENDS_WITH');
                foreach (array_values($query->execute()) as $uid) {
                    $result[] = $this->userStorage->load($uid)->get('mail')->value;
                }

                unset($mail_list[$key]);
                if (!empty($result)) {
                    $mail_list += $result;
                }
            }
        }
        return $mail_list;
    }
}
