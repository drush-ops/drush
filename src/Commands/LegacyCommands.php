<?php
namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Drush;

class LegacyCommands extends DrushCommands
{

    /**
     * Drupal 8 does not support disabling modules. See pm-uninstall command.
     *
     * @command pm-disable
     * @aliases dis
     * @allow-additional-options
     * @hidden
     */
    public function disable() {}

    /**
     * The pm-info command was deprecated. Please see `drush pm-list` and `composer show`
     *
     * @command pm-info
     * @aliases pmi
     * @allow-additional-options
     * @hidden
     */
    public function info() {}

    /**
     * The pm-projectinfo command was deprecated. Please see `drush pm-list` and `composer show`
     *
     * @command pm-projectinfo
     * @allow-additional-options
     * @hidden
     */
    public function projectInfo() {}

    /**
     * The pm-refresh command was deprecated. It is no longer useful.
     *
     * @command pm-refresh
     * @aliases rf
     * @allow-additional-options
     * @hidden
     */
    public function refresh() {}

    /**
     * The pm-updatestatus command was deprecated. Please see `composer show` and `composer outdated`. For security release notification, your project should depend on https://github.com/drupal-composer/drupal-security-advisories.
     *
     * @command pm-updatestatus
     * @aliases ups
     * @allow-additional-options
     * @hidden
     */
    public function updatestatus() {}

    /**
     * The pm-updatecode command was deprecated. Please see `composer outdated` and `composer update`. For security release notification, your project should depend on https://github.com/drupal-composer/drupal-security-advisories.
     *
     * @command pm-updatecode
     * @aliases pm-update,upc
     * @allow-additional-options
     * @hidden
     */
    public function updatecode() {}

    /**
     * The pm-releasenotes command was deprecated. No replacement available.
     *
     * @command pm-releasenotes
     * @aliases rln
     * @allow-additional-options
     * @hidden
     */
    public function releaseNotes() {}

    /**
     * The pm-releases command was deprecated. Please see `composer show <packagename>`
     *
     * @command pm-releases
     * @aliases rl
     * @allow-additional-options
     * @hidden
     */
    public function releases() {}

    /**
     * Make has been removed, in favor of Composer. Use the make-convert command in Drush 8 to quickly upgrade your build to Composer.
     *
     * @command make
     * @aliases make-convert,make-generate,make-lock,make-update
     * @allow-additional-options
     * @hidden
     */
    public function make() {}

    /**
     * dl has been deprecated. Please build your site using Composer. Add new projects with composer require drupal/[project-name]. Use https://www.drupal.org/project/composer_generate to build a composer.json which represents the the enabled modules on your site.
     *
     * @command pm-download
     * @aliases dl
     * @allow-additional-options
     * @hidden
     */
    public function download() {}

    /**
     * @hook validate
     * @param CommandData $commandData
     */
    public function validate(CommandData $commandData) {
        $application = Drush::getApplication();
        $command = $application->get($commandData->input()->getFirstArgument());
        $message = $command->getDescription();
        throw new \Exception($message);
    }
}
