<?php
namespace Drush\Drupal\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Drush\Utils\StringUtils;

/**
 * This class is a good example of how to build a sql-sanitize plugin.
 */
class SanitizeUserFieldsCommands extends DrushCommands implements SanitizePluginInterface
{
    protected $database;
    protected $entityManager;
    protected $entityTypeManager;

    public function __construct($database, $entityManager, $entityTypeManager)
    {
        $this->database = $database;
        $this->entityManager = $entityManager;
        $this->entityTypeManager = $entityTypeManager;
    }

    /**
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return mixed
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * Sanitize string fields associated with the user.
     *
     * @todo Use Drupal services to get field info.
     *
     * @hook post-command sql-sanitize
     *
     * @inheritdoc
     */
    public function sanitize($result, CommandData $commandData)
    {
        $options = $commandData->options();
        $conn = $this->getDatabase();
        $field_definitions = $this->getEntityManager()->getFieldDefinitions('user', 'user');
        $field_storage = $this->getEntityManager()->getFieldStorageDefinitions('user');
        $whitelist_mails = explode(',', $options['whitelist-mails']);
        $whitelist_uids = explode(',', $options['whitelist-uids']);
        foreach (explode(',', $options['whitelist-fields']) as $key) {
            unset($field_definitions[$key], $field_storage[$key]);
        }

        foreach ($field_definitions as $key => $def) {
            $execute = false;
            if (!isset($field_storage[$key]) || $field_storage[$key]->isBaseField()) {
                continue;
            }

            $table = 'user__' . $key;
            $query = $conn->update($table);

            $name = $def->getName();
            $field_type_class = \Drupal::service('plugin.manager.field.field_type')->getPluginClass($def->getType());
            $value_array = $field_type_class::generateSampleValue($def);
            $value = $value_array['value'];
            switch ($def->getType()) {
                case 'email':
                    $query->fields([$name . '_value' => $value]);
                    $execute = true;
                    break;
                case 'string':
                    $query->fields([$name . '_value' => $value]);
                    $execute = true;
                    break;

                case 'string_long':
                    $query->fields([$name . '_value' => $value]);
                    $execute = true;
                    break;

                case 'telephone':
                    $query->fields([$name . '_value' => '15555555555']);
                    $execute = true;
                    break;

                case 'text':
                    $query->fields([$name . '_value' => $value]);
                    $execute = true;
                    break;

                case 'text_long':
                    $query->fields([$name . '_value' => $value]);
                    $execute = true;
                    break;

                case 'text_with_summary':
                    $query->fields([
                    $name . '_value' => $value,
                    $name . '_summary' => $value_array['summary'],
                    ]);
                    $execute = true;
                    break;
            }
            if ($execute) {
                $query->execute();
                $this->entityTypeManager->getStorage('user')->resetCache();
                $this->logger()->success(dt('!table table sanitized.', ['!table' => $table]));
            } else {
                $this->logger()->success(dt('No text fields for users need sanitizing.', ['!table' => $table]));
            }
        }
    }

    /**
     * @hook on-event sql-sanitize-confirms
     *
     * @inheritdoc
     */
    public function messages(&$messages, InputInterface $input)
    {
        $messages[] = dt('Sanitize text fields associated with users.');
    }

    /**
     * @hook option sql-sanitize
     * @option whitelist-fields A comma delimited list of fields exempt from sanitization.
     * @option whitelist-uids A comma delimited list of uids corresponding to the user accounts exempt from sanitization.
     * @option whitelist-mails
     *   A comma delimited list of mails corresponding to the user accounts exempt from sanitization.
     *   wildcard can be used to target all mail accounts on a domain.
     */
    public function options($options = ['whitelist-fields' => '', 'whitelist-uids' => '', 'whitelist-mails' => ''])
    {
    }

    /**
     * Handles wildcard mail addresses and conversion of mails to uids.
     *
     * @hook pre-command sql-sanitize
     */
    public function adjustSanitizeOptions(CommandData $commandData) {
        $input = $commandData->input();
        $whitelist_mails = $input->getOption('whitelist-mails');
        $whitelist_uids = $input->getOption('whitelist-uids');
        $mail_list = $this->handleMailWildcard(StringUtils::csvToArray($whitelist_mails));
        

        $input->setOption('whitelist-mails', implode(",", $mail_list));
    }

    private function handleMailWildcard($mail_list) {
        foreach ($mail_list as $key => $mail) {
            $mail_parts = explode('@', $mail);
            if ($mail_parts[0] === '*') {
                $conn = $this->getDatabase();
                $result = $conn->select('users_field_data', 'ufd')
                    ->fields('ufd', ['mail'])
                    ->condition('mail', "%@" . $mail_parts[1], "LIKE")
                    ->execute()
                    ->fetchAll();
                unset($mail_list[$key]);
                if (!empty($result)) {
                    $mail_list += $result;
                }
            }
        }
        return $mail_list;
    }
}
