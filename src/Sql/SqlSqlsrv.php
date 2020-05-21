<?php
// Install into drush ./vendor/drush/drush/src/Sql

namespace Drush\Sql;

use PDO;
use Consolidation\SiteProcess\Util\Escape;

class SqlSqlsrv extends SqlBase
{
    // The way you pass a sql file when issueing a query.
    public $queryFile = '-i';

    public function command()
    {
        return 'sqlcmd';
    }

    /**
     * Get environment variables to pass to Process.
     */
    public function getEnv()
    {
        $dbSpec = $this->getDbSpec();
        // user environment variable SQLCMDPASSWORD
        $env = array (
            'SQLCMDPASSWORD' => "{$dbSpec['password']}"
        );
        return $env;
    }


    public function creds($hide_password = true)
    {
        $dbSpec = $this->getDbSpec();

        // User is required. Drupal calls it 'username'. SQLCMD calls it 'U'.
        $parameters['U'] = $dbSpec['username'];
        /*
        if (! $hide_password) {
            $parameters['P'] = $dbSpec['password'];
        }
        */

        // Some Drush commands (e.g. site-install) want to connect to the
        // server, but not the database.  Connect to the built-in database.
        $parameters['d'] = empty($dbSpec['database']) ? 'master' : $dbSpec['database'];

        if (isset($dbSpec['host'])) {
            // EMPTY host is not the same as NO host, and is valid (see unix_socket).
            $sqlsrv=$dbSpec['host'];
            if (!empty($dbSpec['port'])) {
                $sqlsrv=$sqlsrv . "," . $dbSpec['port'];
            }
            $parameters['S'] = $sqlsrv;
        }

        return $this->paramsToOptions($parameters);
    }

    /*
     * Helper method to turn associative array into options with values.
     *
     * @return string
     *   A bash fragment.
     */
    public function paramsToOptions($parameters)
    {
        // Turn each parameter into a valid parameter string.
        $parameter_strings = [];
        foreach ($parameters as $key => $value) {
            // Only escape the values, not the keys or the rest of the string.
            $value = Escape::shellArg($value);
            $parameter_strings[] = "-$key $value";
        }

        // Join the parameters and return.
        return implode(' ', $parameter_strings);
    }

    public function silent()
    {
        return '';
    }

    public function createdbSql($dbname, $quoted = false)
    {
        $dbSpec = $this->getDbSpec();
        if ($quoted) {
            $dbname = '[' . $dbname . ']';
        }
        $sql[] = sprintf('DROP DATABASE IF EXISTS %s;', $dbname);
        $sql[] = sprintf('CREATE DATABASE %s;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET ANSI_NULL_DEFAULT OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET ANSI_NULLS OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET ANSI_PADDING OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET ANSI_WARNINGS OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET ARITHABORT OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET AUTO_CLOSE OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET AUTO_SHRINK OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET AUTO_UPDATE_STATISTICS ON;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET CURSOR_CLOSE_ON_COMMIT OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET CURSOR_DEFAULT  GLOBAL;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET CONCAT_NULL_YIELDS_NULL OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET NUMERIC_ROUNDABORT OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET QUOTED_IDENTIFIER OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET RECURSIVE_TRIGGERS OFF ;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET DISABLE_BROKER;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET AUTO_UPDATE_STATISTICS_ASYNC OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET DATE_CORRELATION_OPTIMIZATION OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET ALLOW_SNAPSHOT_ISOLATION OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET PARAMETERIZATION SIMPLE;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET READ_COMMITTED_SNAPSHOT OFF;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET RECOVERY FULL;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET MULTI_USER;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET PAGE_VERIFY CHECKSUM;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET FILESTREAM ( NON_TRANSACTED_ACCESS = OFF );', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET TARGET_RECOVERY_TIME = 0 SECONDS;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET DELAYED_DURABILITY = DISABLED;', $dbname);
        $sql[] = sprintf('ALTER DATABASE %s SET READ_WRITE;', $dbname);

        $db_superuser = $this->getOption('db-su');
        if (isset($db_superuser)) {
            $user = $dbSpec['username'];
            $sql[] = sprintf("IF NOT EXISTS (SELECT * FROM master.sys.server_principals WHERE name = '%s') BEGIN CREATE LOGIN [%s] WITH PASSWORD = '%s'; END;", $user, $user, $dbSpec['password']);
            $sql[] = 'GO';
            $sql[] = sprintf('USE %s;', $dbname);
            $sql[] = sprintf('CREATE USER [%s] FOR LOGIN [%s] WITH DEFAULT_SCHEMA=[dbo];', $user, $user);
            $sql[] = 'GO';
        }
        return implode("\n", $sql);
    }

    /**
     * @inheritdoc
     */
    public function dbExists()
    {
        // Suppress output. We only care about return value.
        return $this->alwaysQuery("SELECT 1;");
    }

    public function listTables()
    {
        $tables = [];
        $this->alwaysQuery('select t.name as name from sys.tables t order by name;');
        if ($out = trim($this->getProcess()->getOutput())) {
            $tables = explode(PHP_EOL, $out);
        }
        return $tables;
    }

    public function dumpCmd($table_selection)
    {
        throw new SqlException('SQL Server doesn\'t support dump directly via sqlcmd. However, you can use SQL Management Studio to generate database scripts.');
    }
}
