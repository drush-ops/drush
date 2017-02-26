<?php
namespace Drush\Commands;

class LegacyCommands extends DrushCommands {

  /**
   * @command pm-disable
   * @aliases dis
   */
  public function disable() {
    $msg = 'Drupal 8 does not support disabling modules. See pm-uninstall command.';
    $this->logger()->notice($msg);
  }

  /**
   * @command pm-info
   * @aliases dis
   */
  public function info() {
    $msg = 'The pm-info command was deprecated. Please see `drush pm-list` and `composer show`';
    $this->logger()->notice($msg);
  }

  /**
   * @command pm-projectinfo
   */
  public function projectInfo() {
    $msg = 'The pm-projectinfo command was deprecated. Please see `drush pm-list` and `composer show`';
    $this->logger()->notice($msg);
  }

  /**
   * @command pm-refresh
   * @aliases rf
   */
  public function refresh() {
    $msg = 'The pm-refresh command was deprecated. It is no longer useful.';
    $this->logger()->notice($msg);
  }

  /**
   * @command pm-updatestatus
   * @aliases ups
   */
  public function updatestatus() {
    $msg = 'The pm-updatestatus command was deprecated. Please see `composer show` and `composer outdated`. For security release notification, your project should depend on https://github.com/drupal-composer/drupal-security-advisories.';
    $this->logger()->notice($msg);
  }

  /**
   * @command pm-updatecode
   * @aliases pm-update,upc
   */
  public function updatecode() {
    $msg = 'The pm-updatecode command was deprecated. Please see `composer outdated` and `composer update`. For security release notification, your project should depend on https://github.com/drupal-composer/drupal-security-advisories.';
    $this->logger()->notice($msg);
  }

  /**
   * @command pm-releasenotes
   * @aliases rln
   */
  public function releaseNotes() {
    $msg = 'The pm-releasenotes command was deprecated. No replacement available.';
    $this->logger()->notice($msg);
  }

  /**
   * @command pm-releases
   * @aliases rl
   */
  public function releases() {
    $msg = 'The pm-releases command was deprecated. Please see `composer show <packagename>`';
    $this->logger()->notice($msg);
  }
}
