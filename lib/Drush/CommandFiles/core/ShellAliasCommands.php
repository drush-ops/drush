<?php
namespace Drush\CommandFiles\core;

use Consolidation\OutputFormatters\StructuredData\AssociativeList;

class ShellAliasCommands {

  /**
   * Print all known shell alias records.
   *
   * @command shell-alias
   * @param string|null $alias 'Shell alias to print',
   * * @field-labels
   *   first: Name
   *   second: Code
   * @default-string-field first
   * @usage drush shell-alias
   *   'List all alias records known to drush.'
   * @usage drush shell-alias pull
   *   Print the value of the shell alias 'pull'.
   * @todo not used in 9.x @bootstrap DRUSH_BOOTSTRAP_NONE
   * @aliases sha
   * @todo not used in 9.x @complete \Drush\CommandFiles\core\ShellAliasCommands::complete
   *
   * @return Consolidation\OutputFormatters\StructuredData\AssociativeList
   */
  public function shellalias($alias = FALSE, $options = ['format' => 'table']) {
    $shell_aliases = drush_get_context('shell-aliases', array());
    if (!$alias) {
      return new AssociativeList($shell_aliases);
    }
    elseif (isset($shell_aliases[$alias])) {
      return new AssociativeList(array($alias => $shell_aliases[$alias]));
    }
  }

  /*
   * An argument provider for shell completion.
   */
  static function complete() {
    if ($all = drush_get_context('shell-aliases', array())) {
      return array('values' => array_keys($all));
    }
  }
}
