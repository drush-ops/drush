<?php

namespace Unish;

/**
 * Tests sql:query command
 *
 *   Tests the sql:query command.
 *
 * @group commands
 * @group sql
 */
class SqlQueryTest extends CommandUnishTestCase
{

    /**
    * Test that the db-prefix option executes successfully.
    */
    public function testSqlQueryDbPrefix()
    {
        $sql_query = 'SELECT uid FROM {users} WHERE uid = 1';

        // Test that it fails without the db-prefix option.
        $this->drush('sql:query', [$sql_query], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('Query failed.', $this->getErrorOutput());
        $this->assertOutputEquals('');

        $this->drush('sql:query', [$sql_query], ['db-prefix' => null]);
        $this->assertOutputEquals('1');
    }

    /**
    * Test that the quote-identifier option works as appropriate.
    */
    public function testSqlQueryQuoteIdentifier()
    {
        if (!$this->isDrupalGreaterThanOrEqualTo('9.0')) {
            $this->markTestSkipped('The quote identifier feature is only available on Drupal 9.0+');
        }

        // Test that it fails without the 'quote-identifier' option present.
        $this->drush('sql:query', ['SELECT [uid] FROM {users} WHERE [uid] = 1'], ['db-prefix' => null], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('Query failed.', $this->getErrorOutput());
        $this->assertOutputEquals('');

        // Test that it resolves the quoted uid field.
        $this->drush('sql:query', ['SELECT [uid] FROM {users} WHERE [uid] = 1'], ['db-prefix' => null, 'quote-identifier' => null]);
        $this->assertOutputEquals('1');

        // Test that potential identifiers within strings are left untouched by default.
        $this->drush('sql:query', ["SELECT '[quoted-field]' AS field_name FROM {users}"], ['db-prefix' => null]);
        $this->assertStringContainsString('[quoted-field]', $this->getOutput());

        // Test that the identifiers within strings are explicitly quoted.
        // Expected behaviour as there's no support for arguments like in PDO.
        $this->drush('sql:query', ["SELECT '[quoted-field]' AS field_name FROM {users}"], ['db-prefix' => null, 'quote-identifier' => null]);
        $this->assertStringContainsString('"quoted-field"', $this->getOutput());
    }
}
