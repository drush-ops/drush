<?php
namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\PropertyList;

class ShellAliasCommands
{

    /**
     * Print all known shell alias records.
     *
     * @command shell:alias
     * @param string|null $alias 'Shell alias to print',
     * @field-labels
     *   first: Name
     *   second: Code
     * @default-string-field second
     * @table-style compact
     * @usage drush shell:alias
     *   List all shell aliases known to Drush.
     * @usage drush shell:alias pull
     *   Print the value of the shell alias 'pull'.
     * @aliases sha,shell-alias
     * @hidden
     *
     * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
     */
    public function shellalias($alias = null, $options = ['format' => 'table'])
    {
        // @todo Not ported to Symfony dispatch.
        $shell_aliases = drush_get_context('shell-aliases', array());
        if (!$alias) {
            return new PropertyList($shell_aliases);
        } elseif (isset($shell_aliases[$alias])) {
            return new PropertyList(array($alias => $shell_aliases[$alias]));
        } else {
            throw new \Exception(dt('Shell alias not found.'));
        }
    }
}
