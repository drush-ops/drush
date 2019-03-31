<?php
namespace Drush\Sql;

use Drush\Utils\StringUtils;

/**
 * Note: when using this trait, also implement ConfigAwareInterface/ConfigAwareTrait.
 *
 * @package Drush\Sql
 */
trait SqlTableSelectionTrait
{

    /**
     * Given a list of all tables, expand the convert the wildcards in the
     * option-provided lists into a list of actual table names.
     *
     * @param array $options An options array as passed to an Annotated Command.
     * @param array $all_tables A list of all eligible tables.
     *
     * @return array
     *   An array of tables with each table name in the appropriate
     *   element of the array.
     */
    public function getExpandedTableSelection($options, $all_tables)
    {
        $table_selection = $this->getTableSelection($options);
        // Get the existing table names in the specified database.
        if (isset($table_selection['skip'])) {
            $table_selection['skip'] = $this->expandAndFilterTables($table_selection['skip'], $all_tables);
        }
        if (isset($table_selection['structure'])) {
            $table_selection['structure'] = $this->expandAndFilterTables($table_selection['structure'], $all_tables);
        }
        if (isset($table_selection['tables'])) {
            $table_selection['tables'] = $this->expandAndFilterTables($table_selection['tables'], $all_tables);
        }
        return $table_selection;
    }

    /**
     * Given the table names in the input array that may contain wildcards (`*`),
     * expand the table names so that the array returned only contains table names
     * that exist in the database.
     *
     * @param array $tables
     *   An array of table names where the table names may contain the
     *   `*` wildcard character.
     * @param array $db_tables
     *   The list of tables present in a database.
     * @return array
     *   An array of tables with non-existant tables removed.
     */
    public function expandAndFilterTables($tables, $db_tables)
    {
        $expanded_tables = $this->ExpandWildcardTables($tables, $db_tables);
        $tables = $this->filterTables(array_merge($tables, $expanded_tables), $db_tables);
        $tables = array_unique($tables);
        sort($tables);
        return $tables;
    }

    /**
     * Expand wildcard tables.
     *
     * @param array $tables
     *   An array of table names, some of which may contain wildcards (`*`).
     * @param array $db_tables
     *   An array with all the existing table names in the current database.
     * @return
     *   $tables array with wildcards resolved to real table names.
     */
    public function expandWildcardTables($tables, $db_tables)
    {
        // Table name expansion based on `*` wildcard.
        $expanded_db_tables = [];
        foreach ($tables as $k => $table) {
            // Only deal with table names containing a wildcard.
            if (strpos($table, '*') !== false) {
                $pattern = '/^' . str_replace('*', '.*', $table) . '$/i';
                // Merge those existing tables which match the pattern with the rest of
                // the expanded table names.
                $expanded_db_tables += preg_grep($pattern, $db_tables);
            }
        }
        return $expanded_db_tables;
    }

    /**
     * Filters tables.
     *
     * @param array $tables
     *   An array of table names to filter.
     * @param array $db_tables
     *   An array with all the existing table names in the current database.
     * @return
     *   An array with only valid table names (i.e. all of which actually exist in
     *   the database).
     */
    public function filterTables($tables, $db_tables)
    {
        // Ensure all the tables actually exist in the database.
        foreach ($tables as $k => $table) {
            if (!in_array($table, $db_tables)) {
                unset($tables[$k]);
            }
        }

        return $tables;
    }

    /**
     * Construct an array that places table names in appropriate
     * buckets based on whether the table is to be skipped, included
     * for structure only, or have structure and data dumped.
     * The keys of the array are:
     * - skip: tables to be skipped completed in the dump
     * - structure: tables to only have their structure i.e. DDL dumped
     * - tables: tables to have structure and data dumped
     *
     * @return array
     *   An array of table names with each table name in the appropriate
     *   element of the array.
     */
    public function getTableSelection($options)
    {
        // Skip large core tables if instructed.  Used by 'sql-drop/sql-dump/sql-sync' commands.
        $skip_tables = $this->getRawTableList('skip-tables', $options);
        // Skip any structure-tables as well.
        $structure_tables = $this->getRawTableList('structure-tables', $options);
        // Dump only the specified tables.  Takes precedence over skip-tables and structure-tables.
        $tables = $this->getRawTableList('tables', $options);

        return ['skip' => $skip_tables, 'structure' => $structure_tables, 'tables' => $tables];
    }

    /**
     * Consult the specified options and return the list of tables specified.
     *
     * @param option_name
     *   The option name to check: skip-tables, structure-tables
     *   or tables.  This function will check both *-key and *-list.
     * @param array $options An options array as passed to an Annotated Command.
     * @return array
     *   Returns an array of tables based on the first option
     *   found, or an empty array if there were no matches.
     */
    public function getRawTableList($option_name, $options)
    {
        $key_list = StringUtils::csvToArray($options[$option_name . '-key']);
        foreach ($key_list as $key) {
            $all_tables = $this->getConfig()->get('sql.' . $option_name, []);
            if (array_key_exists($key, $all_tables)) {
                return $all_tables[$key];
            }
            if ($option_name != 'tables') {
                $all_tables = $this->getConfig()->get('sql.tables', []);
                if (array_key_exists($key, $all_tables)) {
                    return $all_tables[$key];
                }
            }
        }
        $table_list = $options[$option_name . '-list'];
        if (!empty($table_list)) {
            return StringUtils::csvToArray($table_list);
        }

        return [];
    }
}
