<?php

declare(strict_types=1);

namespace Drush\Sql;

use PDO;

class SqlMariaDB extends SqlMysql
{
    public function command(): string
    {
        return 'mariadb';
    }

    public function dumpProgram(): string
    {
        return 'mariadb-dump';
    }

    public function creds($hide_password = true): string
    {
        $parameters = parent::creds($hide_password);

        $dbSpec = $this->getDbSpec();
        $attribs = [
            'ssl_verify_server_cert' => (defined('Pdo\Mysql::ATTR_SSL_CA') ? Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT : PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT),
        ];

        // MariaDB >= 11.4 enables TLS by default. Its client also by default
        // verifies the server certificate.
        // If SSL/TLS server certificate verification is explicitly disabled
        // for the PDO connection used by Drupal, also explicitly disable it
        // for the mariadb client.
        if (($dbSpec['pdo'][$attribs['ssl_verify_server_cert']] ?? null) === false) {
            $parameters .= ' ' . $this->paramsToOptions(['ssl-verify-server-cert' => 'false']);
        }

        return $parameters;
    }
}
