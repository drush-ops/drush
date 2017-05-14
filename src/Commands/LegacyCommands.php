<?php
namespace Drush\Commands;

class LegacyCommands extends DrushCommands
{

    /**
     * @command pm-disable
     * @aliases dis
     * @hidden
     */
    public function disable()
    {
        $msg = 'Drupal 8 does not support disabling modules. See pm-uninstall command.';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-info
     * @aliases pmi
     * @hidden
     */
    public function info()
    {
        $msg = 'The pm-info command was deprecated. Please see `drush pm-list` and `composer show`';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-projectinfo
     * @hidden
     */
    public function projectInfo()
    {
        $msg = 'The pm-projectinfo command was deprecated. Please see `drush pm-list` and `composer show`';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-refresh
     * @aliases rf
     * @hidden
     */
    public function refresh()
    {
        $msg = 'The pm-refresh command was deprecated. It is no longer useful.';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-updatestatus
     * @aliases ups
     * @hidden
     */
    public function updatestatus()
    {
        $msg = 'The pm-updatestatus command was deprecated. Please see `composer show` and `composer outdated`. For security release notification, your project should depend on https://github.com/drupal-composer/drupal-security-advisories.';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-updatecode
     * @aliases pm-update,upc
     * @hidden
     */
    public function updatecode()
    {
        $msg = 'The pm-updatecode command was deprecated. Please see `composer outdated` and `composer update`. For security release notification, your project should depend on https://github.com/drupal-composer/drupal-security-advisories.';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-releasenotes
     * @aliases rln
     * @hidden
     */
    public function releaseNotes()
    {
        $msg = 'The pm-releasenotes command was deprecated. No replacement available.';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-releases
     * @aliases rl
     * @hidden
     */
    public function releases()
    {
        $msg = 'The pm-releases command was deprecated. Please see `composer show <packagename>`';
        $this->logger()->notice($msg);
    }

    /**
     * @command make
     * @aliases make-convert,make-generate,make-lock,make-update
     * @hidden
     */
    public function make()
    {
        $msg = 'Make has been removed, in favor of Composer. Use the make-convert command in Drush 8 to quickly upgrade your build to Composer.';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-download
     * @aliases dl
     * @hidden
     */
    public function download()
    {
        $msg = 'dl has been deprecated. Please build your site using Composer. Add new projects with composer require drupal/[project-name]. Use https://www.drupal.org/project/composer_generate to build a composer.json which represents the the enabled modules on your site.';
        $this->logger()->notice($msg);
    }
}
