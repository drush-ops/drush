<?php
namespace Drush\Commands;

class LegacyCommands extends DrushCommands
{

    /**
     * Drupal 8 does not support disabling modules. See pm:uninstall command.
     *
     * @command pm:disable
     * @aliases dis,pm-disable
     * @hidden
     * @obsolete
     */
    public function disable()
    {
    }

    /**
     * The pm-info command was removed. Please see `drush pm:list` and `composer show`
     *
     * @command pm:info
     * @aliases pmi,pm-info
     * @hidden
     * @obsolete
     */
    public function info()
    {
    }

    /**
     * The pm-projectinfo command was removed. Please see `drush pm:list` and `composer show`
     *
     * @command pm:projectinfo
     * @allow-additional-options
     * @aliases pm-projectinfo
     * @hidden
     * @obsolete
     */
    public function projectInfo()
    {
    }

    /**
     * The pm-refresh command was removed. It is no longer useful.
     *
     * @command pm:refresh
     * @aliases rf,pm-refresh
     * @hidden
     * @obsolete
     */
    public function refresh()
    {
    }

    /**
     * The pm-updatestatus command was removed. Please see `composer show`
     * and `composer update --dry-run`. For security release notification,
     * see `drush pm:security`.
     *
     * @command pm:updatestatus
     * @aliases ups,pm-updatestatus
     * @hidden
     * @obsolete
     */
    public function updatestatus()
    {
    }

    /**
     * The pm-updatecode command was removed. Please see
     * `composer update --dry-run` and `composer update`.
     * For security release notification, see `drush pm:security`.
     *
     * @command pm:updatecode
     * @aliases upc,pm-update,pm-updatecode
     * @hidden
     * @obsolete
     */
    public function updatecode()
    {
    }

    /**
     * The pm-releasenotes command was removed. No replacement available.
     *
     * @command pm:releasenotes
     * @aliases rln,pm-releasenotes
     * @hidden
     * @obsolete
     */
    public function releaseNotes()
    {
    }

    /**
     * The pm-releases command was removed. Please see `composer show <packagename>`
     *
     * @command pm:releases
     * @aliases rl,pm-releases
     * @hidden
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
     * @hidden
     * @obsolete
     */
    public function make()
    {
    }

    /**
     * dl has been removed. Please build your site using Composer. Add new projects with composer require drupal/[project-name]. Use https://www.drupal.org/project/composer_generate to build a composer.json which represents the enabled modules on your site.
     *
     * @command pm:download
     * @aliases dl,pm-download
     * @hidden
     * @obsolete
     */
    public function download()
    {
    }

    /**
     * field-create has been removed. Please try `generate field` command.
     *
     * @command field:create
     * @aliases field-create
     * @hidden
     * @obsolete
     */
    public function field()
    {
    }

    /**
     * core:execute has been removed. Please try `site:ssh` command.
     *
     * @command core:execute
     * @aliases core-execute
     * @hidden
     * @obsolete
     */
    public function execute()
    {
    }
}
