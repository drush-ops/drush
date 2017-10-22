<?php
namespace Drush\Commands;

use Drush\Drush;

class LegacyCommands extends DrushCommands
{

    /**
     * Drupal 8 does not support disabling modules. See pm:uninstall command.
     *
     * @command pm:disable
     * @aliases dis,pm-disable
     * @obsolete
     */
    public function disable()
    {
    }

    /**
     * The pm-info command was deprecated. Please see `drush pm:list` and `composer show`
     *
     * @command pm:info
     * @aliases pmi,pm-info
     * @obsolete
     */
    public function info()
    {
    }

    /**
     * The pm-projectinfo command was deprecated. Please see `drush pm:list` and `composer show`
     *
     * @command pm:projectinfo
     * @allow-additional-options
     * @aliases pm-projectinfo
     * @obsolete
     */
    public function projectInfo()
    {
    }

    /**
     * The pm-refresh command was deprecated. It is no longer useful.
     *
     * @command pm:refresh
     * @aliases rf,pm-refresh
     * @obsolete
     */
    public function refresh()
    {
    }

    /**
     * The pm-updatestatus command was deprecated. Please see `composer show` and `composer outdated`. For security release notification, see `drush pm:security`.
     *
     * @command pm:updatestatus
     * @aliases ups,pm-updatestatus
     * @obsolete
     */
    public function updatestatus()
    {
    }

    /**
     * The pm-updatecode command was deprecated. Please see `composer outdated` and `composer update`. For security release notification, see `drush pm:security`.
     *
     * @command pm:updatecode
     * @aliases upc,pm-update,pm-updatecode
     * @obsolete
     */
    public function updatecode()
    {
    }

    /**
     * The pm-releasenotes command was deprecated. No replacement available.
     *
     * @command pm:releasenotes
     * @aliases rln,pm-releasenotes
     * @obsolete
     */
    public function releaseNotes()
    {
    }

    /**
     * The pm-releases command was deprecated. Please see `composer show <packagename>`
     *
     * @command pm:releases
     * @aliases rl,pm-releases
     * @obsolete
     */
    public function releases()
    {
    }

    /**
     * Make has been removed, in favor of Composer. Use the make-convert command in Drush 8 to quickly upgrade your build to Composer.
     *
     * @command make
     * @aliases make-convert,make-generate,make-lock,make-update
     * @obsolete
     */
    public function make()
    {
    }

    /**
     * dl has been deprecated. Please build your site using Composer. Add new projects with composer require drupal/[project-name]. Use https://www.drupal.org/project/composer_generate to build a composer.json which represents the the enabled modules on your site.
     *
     * @command pm:download
     * @aliases dl,pm-download
     * @obsolete
     */
    public function download()
    {
    }
}
