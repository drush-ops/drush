<?php
namespace Drush\Commands;

/*
 * Common options providers. Use them by adding an annotation to your method.
 */
class OptionsCommands {

  /**
   * @hook option @optionset_proc_build
   * @option ssh-options A string of extra options that will be passed to the ssh command (e.g. "-p 100")
   * @option tty Create a tty (e.g. to run an interactive program).
   * @option escaped Command string already escaped; do not add additional quoting.
   */
  public function optionset_proc_build() {}

  /**
   * @hook option @optionset_get_editor
   * @option editor A string of bash which launches user's preferred text editor. Defaults to ${VISUAL-${EDITOR-vi}}.
   * @option bg Run editor in the background. Does not work with editors such as `vi` that run in the terminal.
   */
  public function optionset_get_editor() {}

  /**
   * @hook option @optionset_sql
   * @option database The DB connection key if using multiple connections in settings.php.
   * @option db-url A Drupal 6 style database URL.
   * @option target The name of a target within the specified database connection. Defaults to default
   */
  public function optionset_sql() {

  }

}


