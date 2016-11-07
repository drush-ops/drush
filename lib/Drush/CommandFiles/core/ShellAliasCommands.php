<?php
namespace Drush\CommandFiles\core;

use Consolidation\OutputFormatters\StructuredData\AssociativeList;

class ShellAliasCommands {

  /**
   * Print all known shell alias records.
   *
   * @command shell-alias
   * @param string|null $alias 'Shell alias to print',
   * @field-labels
   *   first: Name
   *   second: Code
   * @default-string-field second
   * @table-style compact
   * @usage drush shell-alias
   *   'List all shell aliases known to Drush.'
   * @usage drush shell-alias pull
   *   Print the value of the shell alias 'pull'.
   * @aliases sha
   * @todo completion not yet working for Annotated commands.
   * @complete \Drush\CommandFiles\core\ShellAliasCommands::complete
   *
   * @return \Consolidation\OutputFormatters\StructuredData\AssociativeList
   */
  public function shellalias($alias = NULL, $options = ['format' => 'table']) {
    $shell_aliases = drush_get_context('shell-aliases', array());
    if (!$alias) {
      return new AssociativeList($shell_aliases);
    }
    elseif (isset($shell_aliases[$alias])) {
      return new AssociativeList(array($alias => $shell_aliases[$alias]));
    }
    else {
      throw new \Exception(dt('Shell alias not found.'));
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
