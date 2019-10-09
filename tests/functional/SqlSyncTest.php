<?php

/**
 * @file
 *  For now we only test sql-sync in simulated mode.
 *
 *  Future: Using two copies of Drupal, we could test
 *  overwriting one site with another.
 */

namespace Unish;

/**
 * @group slow
 * @group commands
 * @group sql
 */
class SqlSyncTest extends CommandUnishTestCase
{

    public function testSimulatedSqlSync()
    {
        if ($this->isWindows()) {
            $this->markTestSkipped('On Windows, Paths mismatch and confuse rsync.');
        }

        $fixtureSites = [
                'remote' => [
                        'host' => 'server.isp.simulated',
                        'user' => 'www-admin',
                        'ssh' => [
                                'options' => '-o PasswordAuthentication=whatever',
                        ],
                        'paths' => [
                                'drush-script' => '/path/to/drush',
                        ],
                ],
                'local' => [
                ],
        ];
        $this->setUpSettings($fixtureSites, 'synctest');
        $options = [
                'uri' => 'OMIT',
                'simulate' => null,
                'alias-path' => __DIR__ . '/resources/alias-fixtures',
        ];

        $expectedAliasPath = '--alias-path=__DIR__/resources/alias-fixtures';

        // Test simulated simple rsync remote-to-local
        $this->drush('sql:sync', [
                '@synctest.remote',
                '@synctest.local',
        ], $options, '@synctest.local');
        $output = $this->getSimplifiedErrorOutput();
        $this->assertContains("[notice] Simulating: ssh -o PasswordAuthentication=whatever www-admin@server.isp.simulated '/path/to/drush sql-dump --no-interaction --strict=0 --gzip --result-file=auto --backend=json --uri=remote --root=__DIR__/sut", $output);
        $this->assertContains("[notice] Simulating: __DIR__/drush core-rsync @synctest.remote:/simulated/path/to/dump.tgz @synctest.local:__SANDBOX__/tmp/dump.tgz --uri=local --root=__DIR__/sut -- --remove-source-files", $output);
        $this->assertContains("[notice] Simulating: __DIR__/drush sql-query --no-interaction --strict=0 --file=__SANDBOX__/tmp/dump.tgz --file-delete --uri=local --root=__DIR__/sut", $output);

        // Test simulated simple sql:sync local-to-remote
        $this->drush('sql:sync', [
                '@synctest.local',
                '@synctest.remote',
        ], $options, '@synctest.local');
        $output = $this->getSimplifiedErrorOutput();
        $this->assertContains("[notice] Simulating: __DIR__/drush sql-dump --no-interaction --strict=0 --gzip --result-file=auto --backend=json --uri=local --root=__DIR__/sut", $output);
        $this->assertContains("[notice] Simulating: __DIR__/drush core-rsync @synctest.local:/simulated/path/to/dump.tgz @synctest.remote:/tmp/dump.tgz --uri=local --root=__DIR__/sut -- --remove-source-files", $output);
        $this->assertContains("[notice] Simulating: ssh -o PasswordAuthentication=whatever www-admin@server.isp.simulated '/path/to/drush sql-query --no-interaction --strict=0 --file=/tmp/dump.tgz --file-delete --uri=remote --root=__DIR__/sut'", $output);

        // Test simulated remote invoke with a remote runner.
        $this->drush('sql:sync', [
                '@synctest.remote',
                '@synctest.local',
        ], $options, 'user@server/path/to/drupal#sitename');
        $output = $this->getSimplifiedErrorOutput();
        $this->assertContains("[notice] Simulating: ssh -o PasswordAuthentication=no user@server 'drush --no-interaction sql:sync @synctest.remote @synctest.local --uri=sitename --root=/path/to/drupal'", $output);
    }

    /**
     * Covers the following responsibilities.
     *   - A user created on the source site is copied to the destination site.
     *   - The email address of the copied user is sanitized on the destination
     * site.
     *
     * General handling of site aliases will be in sitealiasTest.php.
     */
    public function testLocalSqlSync()
    {
        if ($this->dbDriver()=='sqlite') {
            $this->markTestSkipped('SQL Sync does not apply to SQLite.');
            return;
        }

        $this->setUpDrupal(2, true);
        return $this->localSqlSync();
    }

    public function localSqlSync()
    {

        $options = [
                'yes' => null,
                'uri' => 'OMIT',
        ];

        $stage_options = [
                        'uri' => 'stage',
                ] + $options;

        $users = [
                'joe' => [
                        'uid' => 2,
                        'name' => 'joe.user',
                        'mail' => 'joe.user@myhome.com',
                ],
                'mark' => [
                        'uid' => 3,
                        'name' => 'mark.user',
                        'mail' => 'mark.user@whitelistme.com',
                ],
        ];

        // Add user fields and a test User.
        $this->drush('pm-enable', ['field,text,telephone,comment'], $stage_options + ['yes' => null]);

        foreach ($users as $identifier => $data) {
            $this->drush('php-script', [
                    'user_fields-D8',
                    $data['name'],
                    $data['mail'],
            ], $stage_options + ['script-path' => __DIR__ . '/resources',]);
        }

        // Copy stage to dev, and then sql:sanitize.
        $sync_options = [
                'yes' => null,
                'uri' => 'OMIT',
            // Test wildcards expansion from within sql-sync. Also avoid D8 persistent entity cache.
                'structure-tables-list' => 'cache,cache*',
        ];
        $this->drush('sql-sync', ['@sut.stage', '@sut.dev'], $sync_options);
        $this->drush('sql-sanitize', [], [
                'yes' => null,
                'uri' => 'dev',
                'whitelist-mails' => '*@whitelistme.com',
        ], '@sut.dev');

        // Confirm that the sample user Joe is unchanged on the staging site
        $this->drush('user-information', [$users['joe']['name']], $options + ['format' => 'json'], '@sut.stage');
        $info = $this->getOutputFromJSON($users['joe']['uid']);
        $this->assertEquals($users['joe']['mail'], $info['mail'], 'Email address is unchanged on source site.');
        $this->assertEquals($users['joe']['name'], $info['name']);
        // Get the unchanged pass for the sample user Joe.
        $this->drush('user-information', [$users['joe']['name']], $stage_options + ['field' => 'pass']);
        $original_hashed_pass_joe = $this->getOutput();
        // Get the unchanged pass for the sample user Mark.
        $this->drush('user-information', [$users['mark']['name']], $stage_options + ['field' => 'pass']);
        $original_hashed_pass_mark = $this->getOutput();

        // Confirm that email and password of the sample user Mark (with whitelisted e-mail address) have not been sanitized on the dev site
        $this->drush('user-information', [$users['mark']['name']], $options + [
                        'fields' => 'uid,name,mail,pass',
                        'format' => 'json',
                        'yes' => null,
                ], '@sut.dev');
        $this->getOutputRaw();
        $info = $this->getOutputFromJSON($users['mark']['uid']);
        $this->assertEquals("mark.user@whitelistme.com", $info['mail'], 'Email address of whitelisted user was not sanitized on destination site.');
        $this->assertEquals($users['mark']['name'], $info['name']);
        $this->assertEquals($info['pass'], $original_hashed_pass_mark, 'Password of whitelisted user was not sanitized on destination site.');

        // Confirm that email and password of the sample user Joe have been sanitized on the dev site
        $this->drush('user-information', [$users['joe']['name']], $options + [
                        'fields' => 'uid,name,mail,pass',
                        'format' => 'json',
                        'yes' => null,
                ], '@sut.dev');
        $info = $this->getOutputFromJSON($users['joe']['uid']);
        $this->assertEquals("user+2@localhost.localdomain", $info['mail'], 'Email address was sanitized on destination site.');
        $this->assertEquals($users['joe']['name'], $info['name']);
        $this->assertNotEquals($info['pass'], $original_hashed_pass_joe);

        // Copy stage to dev with --sanitize and a fixed sanitized email
        $sync_options = [
                'yes' => null,
                'uri' => 'OMIT',
            // Test wildcards expansion from within sql-sync. Also avoid D8 persistent entity cache.
                'structure-tables-list' => 'cache,cache*',
        ];
        $this->drush('sql-sync', ['@sut.stage', '@sut.dev'], $sync_options);
        $this->drush('sql-sanitize', [], [
                'yes' => null,
                'sanitize-email' => 'user@mysite.org',
                'whitelist-mails' => '*@whitelistme.com',
                'uri' => 'OMIT',
        ], '@sut.dev');

        // Confirm that the sample user's email address has been sanitized on the dev site
        $this->drush('user-information', [$users['joe']['name']], $options + [
                        'yes' => null,
                        'format' => 'json',
                ], '@sut.dev');
        $info = $this->getOutputFromJSON($users['joe']['uid']);
        $this->assertEquals('user@mysite.org', $info['mail'], 'Email address was sanitized (fixed email) on destination site.');
        $this->assertEquals($users['joe']['name'], $info['name']);

        // Confirm that sanitization of user fields works correctly for non-whitelisted user.
        $fields_joe = [
                'field_user_email' => $users['joe']['mail'],
                'field_user_string' => 'Private info',
                'field_user_string_long' => 'Really private info',
                'field_user_text' => 'Super private info',
                'field_user_text_long' => 'Super duper private info',
                'field_user_text_with_summary' => 'Private',
        ];
        // Assert that field DO NOT contain values.
        foreach ($fields_joe as $field_name => $value) {
            $this->assertUserFieldContents($field_name, $value, $users['joe']['uid']);
        }

        // Assert that field_user_telephone DOES contain "5555555555".
        $this->assertUserFieldContents('field_user_telephone', '5555555555', $users['joe']['uid'], true);

        // Confirm that user fields are not modified for the whitelisted user.
        $fields_mark = [
                'field_user_email' => $users['mark']['mail'],
                'field_user_string' => 'Private info',
                'field_user_string_long' => 'Really private info',
                'field_user_text' => 'Super private info',
                'field_user_text_long' => 'Super duper private info',
                'field_user_text_with_summary' => 'Private',
                'field_user_telephone' => '4104442222'
        ];
        // Assert that field DOES contain values.
        foreach ($fields_mark as $field_name => $value) {
            $this->assertUserFieldContents($field_name, $value, $users['mark']['uid'], true);
        }
    }

    /**
     * Assert that a field on the user entity does or does not contain a value.
     *
     * @param string $field_name
     *   The machine name of the field.
     * @param string $value
     *   The field value.
     * @param integer $uid
     *   The user ID.
     * @param bool $should_contain
     *   Whether the field should contain the value. Defaults to false.
     */
    public function assertUserFieldContents($field_name, $value, $uid, $should_contain = false)
    {
        $table = 'user__' . $field_name;
        $column = $field_name . '_value';
        $this->drush('sql-query', ["SELECT $column FROM $table WHERE entity_id = $uid"], ['uri' => 'OMIT',], '@sut.dev');
        $output = $this->getOutput();
        $this->assertNotEmpty($output);

        if ($should_contain) {
            $this->assertContains($value, $output);
        } else {
            $this->assertNotContains($value, $output);
        }
    }
}
