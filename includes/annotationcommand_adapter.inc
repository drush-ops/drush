/**
 * Adapt Annotation Commands to work with Drush 8.
 *
 * Hooks are ignored; only the main command hook is called.
 *
 * Drush formatters are used instead of the consolidation/output-formatters
 * project.
 *
 * Symfony commands are not used; annotation commands are instead converted
 * to Drush 8 command records.
 *
 * Only annotation commands associated with Drupal modules are discovered
 * and loaded.  Global commands and internal commands must be implemented
 * with the Drush 8 command mechanism.
 */

/**
 * Create the annotation adapter
 */
function annotationcommand_adapter_setup() {

}

/**
 *
 */
function annotationcommand_adapter_register() {

}

/**
 * Run an adapted annotation command for $hook, if it exists.
 */
function annotationcommand_adapter_run($hook) {
  return null;
}
