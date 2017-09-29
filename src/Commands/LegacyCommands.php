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
     * @allow-additional-options
     * @hidden
     */
    public function disable()
    {
        $this->legacyFailureMessage('pm-disable');
    }

    /**
     * The pm-info command was deprecated. Please see `drush pm:list` and `composer show`
     *
     * @command pm:info
     * @aliases pmi,pm-info
     * @allow-additional-options
     * @hidden
     */
    public function info()
    {
        $this->legacyFailureMessage('pm-info');
    }

    /**
     * The pm-projectinfo command was deprecated. Please see `drush pm:list` and `composer show`
     *
     * @command pm:projectinfo
     * @allow-additional-options
     * @aliases pm-projectinfo
     * @hidden
     */
    public function projectInfo()
    {
        $this->legacyFailureMessage('pm-projectinfo');
    }

    /**
     * The pm-refresh command was deprecated. It is no longer useful.
     *
     * @command pm:refresh
     * @aliases rf,pm-refresh
     * @allow-additional-options
     * @hidden
     */
    public function refresh()
    {
        $this->legacyFailureMessage('pm-refresh');
    }

    /**
     * The pm-updatestatus command was deprecated. Please see `composer show` and `composer outdated`. For security release notification, your project should depend on https://github.com/drupal-composer/drupal-security-advisories.
     *
     * @command pm:updatestatus
     * @aliases ups,pm-updatestatus
     * @allow-additional-options
     * @hidden
     */
    public function updatestatus()
    {
        $this->legacyFailureMessage('pm-updatestatus');
    }

    /**
     * The pm-updatecode command was deprecated. Please see `composer outdated` and `composer update`. For security release notification, your project should depend on https://github.com/drupal-composer/drupal-security-advisories.
     *
     * @command pm:updatecode
     * @aliases upc,pm-update,pm-updatecode
     * @allow-additional-options
     * @hidden
     */
    public function updatecode()
    {
        $this->legacyFailureMessage('pm-updatecode');
    }

    /**
     * The pm-releasenotes command was deprecated. No replacement available.
     *
     * @command pm:releasenotes
     * @aliases rln,pm-releasenotes
     * @allow-additional-options
     * @hidden
     */
    public function releaseNotes()
    {
        $this->legacyFailureMessage('pm-releasenotes');
    }

    /**
     * The pm-releases command was deprecated. Please see `composer show <packagename>`
     *
     * @command pm:releases
     * @aliases rl,pm-releases
     * @allow-additional-options
     * @hidden
     */
    public function releases()
    {
        $this->legacyFailureMessage('pm-releases');
    }

    /**
     * Make has been removed, in favor of Composer. Use the make-convert command in Drush 8 to quickly upgrade your build to Composer.
     *
     * @command make
     * @aliases make-convert,make-generate,make-lock,make-update
     * @allow-additional-options
     * @hidden
     */
    public function make()
    {
        $this->legacyFailureMessage('make');
    }

    /**
     * dl has been deprecated. Please build your site using Composer. Add new projects with composer require drupal/[project-name]. Use https://www.drupal.org/project/composer_generate to build a composer.json which represents the the enabled modules on your site.
     *
     * @command pm:download
     * @aliases dl,pm-download
     * @allow-additional-options
     * @hidden
     */
    public function download()
    {
        $this->legacyFailureMessage('pm-download');
    }

    /**
     * Throw and exception taken from the description of the legacy command.
     *
     * @param string $commandName
     * @throws \Exception
     */
    public function legacyFailureMessage($commandName)
    {
        $application = Drush::getApplication();
        $command = $application->get($commandName);
        $message = $command->getDescription();
        throw new \Exception($message);
    }
}
