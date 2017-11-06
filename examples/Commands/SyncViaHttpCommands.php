<?php
namespace Drush\Commands;


use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Drush;

/**
 * Load this commandfile using the --include option - e.g. `drush --include=/path/to/drush/examples`
 */

class SyncViaHttpCommands extends DrushCommands {

  /**
   * When a hook extends a command with additional options, it must
   * implement declare those option(s) in a @hook option like this one.  Doing so will add
   * the option to the help text for the modified command, and will also
   * allow the new option to be specified on the command line.  Without
   * this, Drush will fail with an error when a user attempts to use
   * an unknown option.
   *
   * @hook option sql-sync
   * @option http-sync Copy the database via http instead of rsync.  Value is the url that the existing database dump can be found at.
   * @option http-sync-user Username for the protected directory containing the sql dump.
   * @option http-sync-password Password for the same directory.
   */
  public function optionsetSqlSync() {}

  /**
   * During the pre hook, determine if the http-sync option has been
   * specified.  If it has been, then disable the normal ssh + rsync
   * dump-and-transfer that sql-sync usually does, and transfer the
   * database dump via an http download.
   *
   * @hook pre-command sql-sync
   */
  public function preSqlSync(CommandData $commandData) {
    $sql_dump_download_url = $commandData->input()->getOption('http-sync');
    if (!empty($sql_dump_download_url)) {
      $user = $commandData->input()->getOption('http-sync-user');
      $password = $commandData->input()->getOption('http-sync-password');
      $source_dump_file = $this->downloadFile($sql_dump_download_url, $user, $password);
      $commandData->input()->setOption('target-dump', $source_dump_file);
      $commandData->input()->setOption('no-dump', true);
      $commandData->input()->setOption('no-sync', true);
    }
  }

  /**
   * Downloads a file.
   *
   * Optionally uses user authentication, using either wget or curl, as available.
   */
  protected function downloadFile($url, $user = FALSE, $password = FALSE, $destination = FALSE, $overwrite = TRUE) {
    static $use_wget;
    if ($use_wget === NULL) {
      $use_wget = drush_shell_exec('which wget');
    }

    $destination_tmp = drush_tempnam('download_file');
    if ($use_wget) {
      if ($user && $password) {
        drush_shell_exec("wget -q --timeout=30 --user=%s --password=%s -O %s %s", $user, $password, $destination_tmp, $url);
      }
      else {
        drush_shell_exec("wget -q --timeout=30 -O %s %s", $destination_tmp, $url);
      }
    }
    else {
      if ($user && $password) {
        drush_shell_exec("curl -s -L --connect-timeout 30 --user %s:%s -o %s %s", $user, $password, $destination_tmp, $url);
      }
      else {
        drush_shell_exec("curl -s -L --connect-timeout 30 -o %s %s", $destination_tmp, $url);
      }
    }
    if (!Drush::simulate()) {
      if (!drush_file_not_empty($destination_tmp) && $file = @file_get_contents($url)) {
        @file_put_contents($destination_tmp, $file);
      }
      if (!drush_file_not_empty($destination_tmp)) {
        // Download failed.
        throw new \Exception(dt("The URL !url could not be downloaded.", ['!url' => $url]));
      }
    }
    if ($destination) {
      drush_move_dir($destination_tmp, $destination, $overwrite);
      return $destination;
    }
    return $destination_tmp;
  }
}
