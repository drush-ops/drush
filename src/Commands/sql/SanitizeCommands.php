<?php

namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Drush\Sql\SqlTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
     * Note that these commandfiles are not automatically loaded when supplied by Drupal modules (since this command
     * only bootstraps to CONFIG). You may load these module-supplied commandfiles via --include option. For example,
     * `drush --include modules/contrib/module-name/src/Commands sql-sanitize`. Also note that these commandfiles may not
     * use dependency injection.
     *
     * @command sql-sanitize
     *
     * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION
     * @description Run sanitization operations on the current database.
     * @option db-prefix Enable replacement of braces in sanitize queries.
     * @option sanitize-email The pattern for test email addresses in the
     *   sanitization operation, or "no" to keep email addresses unchanged. May
     *   contain replacement patterns %uid, %mail or %name.
     * @option sanitize-password The password to assign to all accounts in the
     *   sanitization operation, or "no" to keep passwords unchanged.
     * @option whitelist-fields A comma delimited list of fields exempt from sanitization.
     * @aliases sqlsan
     * @usage drush sql-sanitize --sanitize-password=no
     *   Sanitize database without modifying any passwords.
     * @usage drush sql-sanitize --whitelist-fields=field_biography,field_phone_number
     *   Sanitizes database but exempts two user fields from modification.
     */
    public function sanitize($options = ['db-prefix' => false, 'sanitize-email' => 'user+%uid@localhost.localdomain', 'sanitize-password' => 'password', 'whitelist-fields' => ''])
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
            drush_print(dt('The following operations will be performed:'));
            foreach ($messages as $message) {
                drush_print('* '. $message);
            }
        }
        if (!$this->io()->confirm(dt('Do you want to sanitize the current database?'))) {
            throw new UserAbortException();
        }

        // All sanitize operations defined in post-command hooks, including Drush
        // core sanitize routines. See \Drush\Commands\sql\SanitizePluginInterface.
    }
}
