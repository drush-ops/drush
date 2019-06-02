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
    protected $database;
    use CustomEventAwareTrait;

    public function __construct($database) {
       $this->database = $database;
    }

    /**
     * @return mixed
     */
    public function getDatabase() {
        return $this->database;
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
     *   Sanitizes database but exempts two user accounts from modification based on uids.
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
     * Handles wildcard mail addresses and conversion of user mails to uids.
     *
     * @hook pre-command sql-sanitize
     */
    public function handleMilWhitelist(CommandData $commandData) {
        $input = $commandData->input();
        $whitelist_mails = StringUtils::csvToArray($input->getOption('whitelist-mails'));
        var_dump($input->getOption('whitelist-mails'));
        $whitelist_uids = StringUtils::csvToArray($input->getOption('whitelist-uids'));
        $uids_mail_list = $this->uidsByMails($this->handleMailWildcard($whitelist_mails));
//                var_dump($uids_mail_list);
//                var_dump($whitelist_uids);
        $input->setOption('whitelist-uids', implode(",", array_merge($whitelist_uids, $uids_mail_list)));
    }

    /**
     * Helper function which returns user ids based on their mail addresses.
     *
     * @param array $mail_list
     *
     * @return array
     */
    private function uidsByMails($mail_list) {
        //print_r($mail_list);
        if (empty($mail_list)) {
            return [];
        }
        $conn = $this->getDatabase();
        return $conn->select('users_field_data', 'ufd')
            ->fields('ufd', ['uid'])
            ->condition('mail', $mail_list, 'IN')
            ->execute()
            ->fetchCol(0);
    }

    /**
     * Helper function which returns a list of all mails based on wildcard.
     *
     * @param array $mail_list
     *
     * @return mixed
     */
    private function handleMailWildcard($mail_list) {
        foreach ($mail_list as $key => $mail) {
            $mail_parts = explode('@', $mail);
            if ($mail_parts[0] === '*') {
                $conn = $this->getDatabase();
                $result = $conn->select('users_field_data', 'ufd')
                    ->fields('ufd', ['mail'])
                    ->condition('mail', "%@" . $mail_parts[1], "LIKE")
                    ->execute()
                    ->fetchCol(0);

                unset($mail_list[$key]);
                if (!empty($result)) {
                    $mail_list += $result;
                }
            }
        }
        return $mail_list;
    }
}
