<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;

/**
 * Topic commands.
 * Any commandfile may add topics.
 * Set 'topic' => TRUE to indicate the command is a topic (REQUIRED)
 * Begin the topic name with the name of the commandfile (just like
 * any other command).
 */
class DocsCommands extends DrushCommands {

  /**
   * README.md
   *
   * @command docs-readme
   * @hidden
   * @topic
   */
  public function readme() {
    self::printFile(DRUSH_BASE_PATH. '/README.md');
  }

  /**
   * Drush's support for Git Bisect.
   *
   * @command docs-bisect
   * @hidden
   * @topic
   */
  public function bisect() {
    self::printFile(DRUSH_BASE_PATH. '/examples/git-bisect.example.sh');
  }

  /**
   * Bashrc customization examples for Drush.
   *
   * @command docs-bashrc
   * @hidden
   * @topic
   */
  public function bashrc() {
    self::printFile(DRUSH_BASE_PATH. '/examples/example.bashrc\'');
  }

  /**
   * Configuration overview with examples from example.drushrc.php.
   *
   * @command docs-configuration
   * @hidden
   * @topic
   */
  public function config() {
    self::printFile(DRUSH_BASE_PATH. '/examples/example.drushrc.php');
  }

  /**
   * Drupal configuration export instructions, including customizing configuration by environment.
   *
   * @command docs-config-exporting
   * @hidden
   * @topic
   */
  public function configExport() {
    self::printFile(DRUSH_BASE_PATH. '/docs/config-exporting.md');
  }

  /**
   * Site aliases overview on creating your own aliases for commonly used Drupal sites with examples from example.aliases.drushrc.php.
   *
   * @command docs-aliases
   * @hidden
   * @topic
   */
  public function siteAliases() {
    self::printFile(DRUSH_BASE_PATH. '/examples/example.aliases.drushrc.php');
  }

  /**
   * Bastion server configuration: remotely operate on a Drupal sites behind a firewall.
   *
   * @command docs-bastion
   * @hidden
   * @topic
   */
  public function bastion() {
    self::printFile(DRUSH_BASE_PATH. '/docs/bastion.md');
  }

  /**
   * Bootstrap explanation: how Drush starts up and prepares the Drupal environment for use with the command.
   *
   * @command docs-bootstrap
   * @hidden
   * @topic
   */
  public function bootstrap() {
    self::printFile(DRUSH_BASE_PATH. '/docs/bootstrap.md');
  }

  /**
   * Crontab instructions for running your Drupal cron tasks via `drush cron`.
   *
   * @command docs-cron
   * @hidden
   * @topic
   */
  public function cron() {
    self::printFile(DRUSH_BASE_PATH. '/docs/cron.md');
  }

  /**
   * A script consisting of simple sequences of Drush statements.
   *
   * @command docs-scripts
   * @hidden
   * @topic
   */
  public function scripts() {
    self::printFile(DRUSH_BASE_PATH. '/docs/shellscripts.md');
  }

  /**
   * Creating your own aliases for commonly used Drush commands.
   *
   * @command docs-shell-aliases
   * @hidden
   * @topic
   */
  public function shellAliases() {
    self::printFile(DRUSH_BASE_PATH. '/docs/shellaliases.md');
  }

  /**
   * Instructions on creating your own Drush commands.
   *
   * @command docs-commands
   * @hidden
   * @topic
   */
  public function commands() {
    self::printFile(DRUSH_BASE_PATH. '/docs/commands.md');
  }

  /**
   * Drush API
   *
   * @command docs-api
   * @hidden
   * @topic
   */
  public function api() {
    self::printFile(DRUSH_BASE_PATH. 'docs//drush.api.php');
  }

  /**
   * Contexts overview explaining how Drush manages command line options and configuration file settings.
   *
   * @command docs-context
   * @hidden
   * @topic
   */
  public function context() {
    self::printFile(DRUSH_BASE_PATH. 'docs/context.md');
  }

  /**
   * Example Drush script.
   *
   * @command docs-examplescript
   * @hidden
   * @topic
   */
  public function exampleScript() {
    self::printFile(DRUSH_BASE_PATH. '/examples/helloworld.script');
  }

  /**
   * Example Drush command file.
   *
   * @command docs-examplecommand
   * @hidden
   * @topic
   */
  public function exampleCommand() {
    self::printFile(DRUSH_BASE_PATH. '/examples/sandwich.drush.inc');
  }

  /**
   * Example commandfile that extends sql-sync to enable development modules in the post-sync hook.
   *
   * @command docs-example-sync-extension
   * @hidden
   * @topic
   */
  public function syncEnable() {
    self::printFile(DRUSH_BASE_PATH. '/examples/sync_enable.drush.inc');
  }

  /**
   * Example Drush commandfile that extends sql-sync to allow transfer of the sql dump file via http rather than ssh and rsync.
   *
   * @command docs-example-sync-via-http
   * @hidden
   * @topic
   */
  public function syncHttp() {
    self::printFile(DRUSH_BASE_PATH. '/examples/sync_via_http.drush.inc');
  }

  /**
   * Example policy file.
   *
   * @command docs-policy
   * @hidden
   * @topic
   */
  public function policy() {
    self::printFile(DRUSH_BASE_PATH. '/examples/policy.drush.inc');
  }

  /**
   * Strict option handling, and how commands that use it differ from regular Drush commands.
   *
   * @command docs-strict-options
   * @hidden
   * @topic
   */
  public function strictOptions() {
    self::printFile(DRUSH_BASE_PATH. '/docs/strict-options.md');
  }
}