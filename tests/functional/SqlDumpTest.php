<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\CacheCommands;
use Drush\Commands\core\CacheRebuildCommands;
use Drush\Commands\sql\SqlCommands;
use Symfony\Component\Filesystem\Path;

/**
 * Tests for sql:dump commands.
 *
 * @group commands
 * @group sql
 * @group slow
 */
class SqlDumpTest extends CommandUnishTestCase
{
  /**
   * Test that a dump file is created successfully.
   */
    public function testSqlDump()
    {
        if ($this->dbDriver() == 'sqlite') {
            $this->markTestSkipped('SQL Dump does not apply to SQLite.');
            return;
        }

        $this->setUpDrupal(1, true);
        $full_dump_file_path = Path::join(self::getSandbox(), 'full_db.sql');

        $options = [
            'result-file' => $full_dump_file_path,
            // Last 5 entries are for D8+
            'skip-tables-list' => 'hist*,cache*,router,config*,watchdog,key_valu*',
            'yes' => null,
        ];

        // In Drupal 9.1+, cache_discovery et. al. do not exist until after a cache rebuild.
        $this->drush(CacheRebuildCommands::REBUILD, []);

        $this->drush(SqlCommands::DUMP, [], $options + ['simulate' => null]);
        $expected = $this->dbDriver() == 'mysql' ? '--ignore-table=unish_dev.cache_discovery' : '--exclude-table=cache_discovery';
        $this->assertStringContainsString($expected, $this->getErrorOutput());

        // Test --extra-dump option
        if ($this->dbDriver() == 'mysql') {
            $this->drush(SqlCommands::DUMP, [], array_merge($options, [], ['extra-dump' => '--skip-add-drop-table']));
            $this->assertFileExists($full_dump_file_path);
            $full_dump_file = file_get_contents($full_dump_file_path);
            $this->assertStringNotContainsString('DROP TABLE IF EXISTS', $full_dump_file);
        }


        // First, do a test without any aliases, and dump the whole database
        $this->drush(SqlCommands::DUMP, [], $options);
        $this->assertFileExists($full_dump_file_path);
        $full_dump_file = file_get_contents($full_dump_file_path);
        // Test that we have sane contents.
        $this->assertStringContainsString('menu_tree', $full_dump_file);
        // Test skip-files-list and wildcard expansion.
        $this->assertStringNotContainsString('CREATE TABLE `key_value', $full_dump_file);
        // Next, set up an alias file and run a couple of simulated
        // tests to see if options are propagated correctly.
        // Control: insure options are not set when not specified
        unset($options['skip-tables-list']);
        unlink($full_dump_file_path);
        $this->drush(SqlCommands::DUMP, [], $options);
        $this->assertFileExists($full_dump_file_path);
        $full_dump_file = file_get_contents($full_dump_file_path);
        // Test that we have sane contents.
        $expected = $this->dbDriver() == 'mysql' ? 'CREATE TABLE `menu_tree' : 'CREATE TABLE public.menu_tree';
        $this->assertStringContainsString($expected, $full_dump_file);
        // Test absence of skip-files-list.
        $expected = $this->dbDriver() == 'mysql' ? 'CREATE TABLE `key_value' : 'CREATE TABLE public.key_value';
        $this->assertStringContainsString($expected, $full_dump_file);

    // @todo Aliases to local sites are no longer supported. Throw exception?
    //    $aliasPath = self::getSandbox() . '/aliases';
    //    mkdir($aliasPath);
    //    $aliasFile = $aliasPath . '/bar.aliases.drushrc.php';
    //    $aliasContents = <<<EOD
    //  <?php
    //  // Written by Unish. This file is safe to delete.
    //  \$aliases['test'] = array(
    //    'root' => '$root',
    //    'uri' => '$uri',
    //    'site' => 'stage',
    //    'command-specific' => array(
    //      'sql:dump' => array(
    //        'skip-tables-list' => 'hist*,cache*,router,config*,watchdog,key_valu*',
    //      ),
    //    ),
    //  );
    //EOD;
    //    file_put_contents($aliasFile, $aliasContents);
    //    $options['alias-path'] = $aliasPath;
    //    unlink($full_dump_file_path);
    //    // Now run again with an alias, and test to see if the option is there
    //    $this->drush('sql:dump', array(), array_merge($options), '@test');
    //    $this->assertFileExists($full_dump_file_path);
    //    $full_dump_file = file_get_contents($full_dump_file_path);
    //    // Test that we have sane contents.
    //    $this->assertStringContainsString('queue', $full_dump_file);
    //    // Test skip-files-list and wildcard expansion.
    //    $this->assertStringNotContainsString('CREATE TABLE `key_value', $full_dump_file);
    //    // Repeat control test:  options not recovered in absence of an alias.
    //    unlink($full_dump_file_path);
    //    $this->drush('sql:dump', array(), $options);
    //    $this->assertFileExists($full_dump_file_path);
    //    $full_dump_file = file_get_contents($full_dump_file_path);
    //    // Test that we have sane contents.
    //    $this->assertStringContainsString('queue', $full_dump_file);
    //    // Test absence of skip-files-list.
    //    $this->assertStringContainsString('CREATE TABLE `key_value', $full_dump_file);
    //    // Now run yet with @self, and test to see that Drush can recover the option
    //    // --skip-tables-list, defined in @test.
    //    unlink($full_dump_file_path);
    //    $this->drush('sql:dump', array(), $options, '@self');
    //    $this->assertFileExists($full_dump_file_path);
    //    $full_dump_file = file_get_contents($full_dump_file_path);
    //    // Test that we have sane contents.
    //    $this->assertStringContainsString('queue', $full_dump_file);
    //    // Test absence of skip-files-list.
    //    $this->assertStringNotContainsString('CREATE TABLE `key_value', $full_dump_file);
    }
}
