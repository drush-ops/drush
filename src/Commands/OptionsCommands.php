<?php
namespace Drush\Commands;

/*
 * Common options providers. Use them by adding an annotation to your method.
 */
class OptionsCommands
{

    /**
     * @hook option @optionset_proc_build
     * @option ssh-options A string of extra options that will be passed to the ssh command (e.g. "-p 100")
     * @option tty Create a tty (e.g. to run an interactive program).
     * @option escaped Command string already escaped; do not add additional quoting.
     */
    public function optionsetProcBuild()
    {
    }

    /**
     * @hook option @optionset_get_editor
     * @option editor A string of bash which launches user's preferred text editor. Defaults to ${VISUAL-${EDITOR-vi}}.
     * @option bg Run editor in the background. Does not work with editors such as `vi` that run in the terminal.
     */
    public function optionsetGetEditor()
    {
    }

    /**
     * @hook option @optionset_ssh
     * @option ssh-options A string appended to ssh command during rsync, sql-sync, etc.
     */
    public function optionsetSsh()
    {
    }

    /**
     * @hook option @optionset_sql
     * @option database The DB connection key if using multiple connections in settings.php.
     * @option db-url A Drupal 6 style database URL.
     * @option target The name of a target within the specified database connection. Defaults to default
     */
    public function optionsetSql($options = ['database' => 'default', 'target' => 'default'])
    {
    }

    /**
     * @hook option @optionset_table_selection
     * @option skip-tables-key A key in the $skip_tables array. @see example.drushrc.php
     * @option structure-tables-key A key in the $structure_tables array. @see example.drushrc.php
     * @option tables-key A key in the $tables array.
     * @option skip-tables-list A comma-separated list of tables to exclude completely.
     * @option structure-tables-list A comma-separated list of tables to include for structure, but not data.
     * @option tables-list A comma-separated list of tables to transfer.
     */
    public function optionsetTableSelection($command, $annotationData)
    {
    }
}
