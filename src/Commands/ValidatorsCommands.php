<?php
namespace Drush\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Input\Input;

/*
 * Common validation providers. Use them by adding an annotation to your method.
 */
class ValidatorsCommands
{

    /**
     * Validate that passed entity names are valid.
     * @see \Drush\Commands\core\ViewsCommands::execute for an example.
     *
     * @hook validate @validate-entity-load
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     * @return \Consolidation\AnnotatedCommand\CommandError|null
     */
    public function validateEntityLoad(CommandData $commandData)
    {
        list($entity_type, $arg_name) = explode(' ', $commandData->annotationData()->get('validate-entity-load', null));
        $names = StringUtils::csvToArray($commandData->input()->getArgument($arg_name));
        $loaded = \Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple($names);
        if ($missing = array_diff($names, array_keys($loaded))) {
            $msg = dt('Unable to load the !type: !str', ['!type' => $entity_type, '!str' => implode(', ', $missing)]);
            return new CommandError($msg);
        }
    }

    /**
     * Validate that passed module names are enabled. We use pre-init phase because interact() methods run early and they
     * need to know that their module is enabled (e.g. image-flush).
     *
     * @see \Drush\Commands\core\WatchdogCommands::show for an example.
     *
     * @hook pre-init @validate-module-enabled
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     * @return \Consolidation\AnnotatedCommand\CommandError|null
     */
    public function validateModuleEnabled(Input $input, AnnotationData $annotationData)
    {
        $names = StringUtils::csvToArray($annotationData->get('validate-module-enabled'));
        $loaded = \Drupal::moduleHandler()->getModuleList();
        if ($missing = array_diff($names, array_keys($loaded))) {
            $msg = dt('Missing module: !str', ['!str' => implode(', ', $missing)]);
            throw new \Exception($msg);
        }
    }

    /**
     * Validate that the file path exists.
     *
     * Annotation value should be the name of the argument containing the path.
     *
     * @hook validate @validate-file-exists
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     * @return \Consolidation\AnnotatedCommand\CommandError|null
     */
    public function validateFileExists(CommandData $commandData)
    {
        $missing = [];
        $arg_names = _convert_csv_to_array($commandData->annotationData()->get('validate-file-exists', null));
        foreach ($arg_names as $arg_name) {
            if ($commandData->input()->hasArgument($arg_name)) {
                $path = $commandData->input()->getArgument($arg_name);
            } elseif ($commandData->input()->hasOption($arg_name)) {
                $path = $commandData->input()->getOption($arg_name);
            }
            if (!empty($path) && !file_exists($path)) {
                $missing[] = $path;
            }
            unset($path);
        }

        if ($missing) {
            $msg = dt('File(s) not found: !paths', ['!paths' => implode(', ', $missing)]);
            return new CommandError($msg);
        }
    }

    /**
     * Validate that required PHP extension exists.
     *
     * Annotation value should be extension name. If multiple, delimit by a comma.
     *
     * @hook validate @validate-php-extension
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     * @return \Consolidation\AnnotatedCommand\CommandError|null
     */
    public function validatePHPExtension(CommandData $commandData)
    {
        $missing = [];
        $arg_names = _convert_csv_to_array($commandData->annotationData()->get('validate-php-extension', null));
        foreach ($arg_names as $arg_name) {
            if (!extension_loaded($arg_name)) {
                $missing[] = $arg_name;
            }
        }

        if ($missing) {
            $args = array('!command' => $commandData->input(), '!dependencies' => implode(', ', $missing));
            return new CommandError(dt('Command !command needs the following PHP extensions installed and enabled: !dependencies.', $args));
        }
    }

    /**
     * Validate that the permission exists.
     *
     * Annotation value should be the name of the argument/option containing the permission(s).
     *
     * @hook validate @validate-permissions
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     * @return \Consolidation\AnnotatedCommand\CommandError|null
     */
    public function validatePermissions(CommandData $commandData)
    {
        $missing = [];
        $arg_or_option_name = $commandData->annotationData()->get('validate-permissions', null);
        if ($commandData->input()->hasArgument($arg_or_option_name)) {
            $permissions = StringUtils::csvToArray($commandData->input()->getArgument($arg_or_option_name));
        } else {
            $permissions = StringUtils::csvToArray($commandData->input()->getOption($arg_or_option_name));
        }
        $all_permissions = array_keys(\Drupal::service('user.permissions')->getPermissions());
        $missing = array_diff($permissions, $all_permissions);
        if ($missing) {
            $msg = dt('Permission(s) not found: !perms', ['!perms' => implode(', ', $missing)]);
            return new CommandError($msg);
        }
    }
}
