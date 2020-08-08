<?php

namespace Drush\Drupal\Commands\sql;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

class SanitizeCommands extends DrushCommands implements CustomEventAwareInterface
{

    use CustomEventAwareTrait;

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
     * @usage drush sql:sanitize --allowlist-fields=field_biography,field_phone_number
     *   Sanitizes database but exempts two user fields from modification.
     * @topics docs:hooks
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
}
