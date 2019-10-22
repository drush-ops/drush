<?php

namespace Drush\Commands\config;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Drush\Commands\DrushCommands;

/**
 * Non-service based wrapper for the service based config:import command.
 *
 * Importing config that contains newly enabled modules with new service
 * dependencies can only be done using the UpdateKernel.
 *
 * The actual config importer is a service and is only discovered _after_ Drupal
 * is already bootstrapped, but this would cause the default DrupalKernel to be
 * bootstrapped.
 *
 * Since this wrapper is not a service it can be discovered early and bootstrap
 * the UpdateKernel. We can then invoke the actual service based command
 * statically through \Drupal::service().
 *
 * @see \Drush\Drupal\Commands\config\ConfigImportCommands
 * @see https://www.drupal.org/node/3067480
 */
class ConfigImportCommands extends DrushCommands
{

    /**
     * Import config from a config directory.
     *
     * @command config:import
     * @param $label A config directory label (i.e. a key in \$config_directories array in settings.php).
     * @interact-config-label
     * @option diff Show preview as a diff.
     * @option preview Deprecated. Format for displaying proposed changes. Recognized values: list, diff.
     * @option source An arbitrary directory that holds the configuration files. An alternative to label argument
     * @option partial Allows for partial config imports from the source directory. Only updates and new configs will be processed with this flag (missing configs will not be deleted). No config transformation happens.
     * @aliases cim,config-import
     * @kernel update
     * @bootstrap full
     */
    public function import($label = null, $options = ['preview' => 'list', 'source' => self::REQ, 'partial' => false, 'diff' => false])
    {
        /** @var \Drush\Drupal\Commands\config\ConfigImportCommands $config_import_commands_service */
        $config_import_commands_service = \Drupal::service('config.import.commands');
        return $config_import_commands_service->import($label, $options);
    }

    /**
     * @hook validate config-import
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     * @return \Consolidation\AnnotatedCommand\CommandError|null
     */
    public function validate(CommandData $commandData)
    {
        $msgs = [];
        if ($commandData->input()->getOption('partial') && !\Drupal::moduleHandler()->moduleExists('config')) {
            $msgs[] = 'Enable the config module in order to use the --partial option.';
        }

        if ($source = $commandData->input()->getOption('source')) {
            if (!file_exists($source)) {
                $msgs[] = 'The source directory does not exist.';
            }
            if (!is_dir($source)) {
                $msgs[] = 'The source is not a directory.';
            }
        }

        if ($msgs) {
            return new CommandError(implode(' ', $msgs));
        }
    }
}
